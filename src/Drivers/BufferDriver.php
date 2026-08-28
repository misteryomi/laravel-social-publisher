<?php

namespace Misteryomi\SocialPublisher\Drivers;

use Carbon\CarbonInterface;
use Misteryomi\SocialPublisher\Contracts\SocialDriverContract;
use Misteryomi\SocialPublisher\Enums\Platform;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BufferDriver implements SocialDriverContract
{
    private const CREATE_POST = <<<'GQL'
    mutation CreatePost($input: CreatePostInput!) {
      createPost(input: $input) {
        ... on PostActionSuccess { post { id } }
        ... on MutationError { message }
      }
    }
    GQL;

    public function __construct(private readonly array $config) {}

    public function driverName(): string
    {
        return 'Buffer';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['access_token'] ?? null);
    }

    /**
     * @return array<int, array{id: string, service: string, name: string}>
     */
    public function profiles(): array
    {
        $ttl = (int) ($this->config['profiles_cache_ttl'] ?? 300);

        return Cache::remember('social_publisher_buffer_profiles', $ttl, function () {
            $account = $this->graphql('query { account { organizations { id } } }');
            $orgId   = $account['account']['organizations'][0]['id'] ?? null;

            if ($orgId === null) {
                throw new \RuntimeException('No Buffer organization found for this token.');
            }

            $data = $this->graphql(
                'query GetChannels($input: ChannelsInput!) { channels(input: $input) { id name service } }',
                ['input' => ['organizationId' => $orgId]],
            );

            return collect($data['channels'] ?? [])
                ->map(fn ($c) => [
                    'id'      => (string) ($c['id'] ?? ''),
                    'service' => (string) ($c['service'] ?? ''),
                    'name'    => (string) ($c['name'] ?? $c['service'] ?? ''),
                ])
                ->filter(fn ($c) => $c['id'] !== '')
                ->values()
                ->all();
        });
    }

    public function profileIdForPlatform(string $platform): ?string
    {
        $service = Platform::tryFrom($platform)?->bufferService() ?? $platform;

        $match = collect($this->profiles())->first(fn ($p) => $p['service'] === $service);

        return $match['id'] ?? null;
    }

    /**
     * @param  array<int, string>  $profileIds
     * @return array<int, string>
     */
    public function queue(
        string $text,
        array $profileIds,
        ?CarbonInterface $scheduledAt = null,
        ?string $imageUrl = null,
        ?string $platform = null,
    ): array {
        if (! $this->isConfigured()) {
            Log::warning('SocialPublisher/Buffer: access token not set — skipping post');

            return [];
        }

        $channelIds    = array_values(array_filter($profileIds));
        $publicImageUrl = $this->publicUrl($imageUrl);

        if ($channelIds === []) {
            return [];
        }

        $postIds = [];

        foreach ($channelIds as $channelId) {
            $input = [
                'text'           => $text,
                'channelId'      => $channelId,
                'schedulingType' => 'automatic',
                'mode'           => $scheduledAt !== null ? 'customScheduled' : 'addToQueue',
            ];

            if (in_array($platform, ['instagram', 'facebook'], true)) {
                $input['mediaType'] = 'POST';
            }

            if ($scheduledAt !== null) {
                $input['dueAt'] = $scheduledAt->toIso8601String();
            }

            if ($publicImageUrl !== null) {
                $input['assets'] = [['image' => ['url' => $publicImageUrl]]];
            }

            try {
                $postIds = array_merge($postIds, $this->createPost($input, $channelId));
            } catch (\RuntimeException $e) {
                Log::warning('SocialPublisher/Buffer: post exception', [
                    'error'   => $e->getMessage(),
                    'channel' => $channelId,
                ]);
            }
        }

        return $postIds;
    }

    /**
     * @param  array<int, string>  $profileIds
     * @return array<int, string>
     */
    public function queueReel(
        string $text,
        array $profileIds,
        string $videoUrl,
        ?CarbonInterface $scheduledAt = null,
        ?string $platform = null,
    ): array {
        if (! $this->isConfigured()) {
            Log::warning('SocialPublisher/Buffer: access token not set — skipping reel');

            return [];
        }

        $channelIds    = array_values(array_filter($profileIds));
        $publicVideoUrl = $this->publicUrl($videoUrl);

        if ($channelIds === [] || $publicVideoUrl === null) {
            return [];
        }

        $postIds = [];

        foreach ($channelIds as $channelId) {
            $input = [
                'text'           => $text,
                'channelId'      => $channelId,
                'schedulingType' => 'automatic',
                'mode'           => $scheduledAt !== null ? 'customScheduled' : 'addToQueue',
                'mediaType'      => $platform === 'tiktok' ? 'VIDEO' : 'REEL',
                'assets'         => [['video' => ['url' => $publicVideoUrl]]],
            ];

            if ($scheduledAt !== null) {
                $input['dueAt'] = $scheduledAt->toIso8601String();
            }

            try {
                $postIds = array_merge($postIds, $this->createPost($input, $channelId));
            } catch (\RuntimeException $e) {
                Log::warning('SocialPublisher/Buffer: reel exception', [
                    'error'   => $e->getMessage(),
                    'channel' => $channelId,
                ]);
            }
        }

        return $postIds;
    }

    /**
     * Run createPost, retrying with notification scheduling if the channel is a
     * Facebook Group (which rejects the automatic scheduling type).
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function createPost(array $input, string $channelId): array
    {
        $data   = $this->graphql(self::CREATE_POST, ['input' => $input]);
        $result = $data['createPost'] ?? [];

        if (isset($result['post']['id'])) {
            Log::info('SocialPublisher/Buffer: post queued', [
                'channel' => $channelId,
                'post'    => $result['post']['id'],
            ]);

            return [(string) $result['post']['id']];
        }

        $message = (string) ($result['message'] ?? '');

        // Facebook Group channels require notification scheduling, not automatic.
        if (str_contains($message, 'Facebook Group channels require notification scheduling')) {
            $input['schedulingType'] = 'notification';

            $retry       = $this->graphql(self::CREATE_POST, ['input' => $input]);
            $retryResult = $retry['createPost'] ?? [];

            if (isset($retryResult['post']['id'])) {
                Log::info('SocialPublisher/Buffer: post queued (Facebook Group notification mode)', [
                    'channel' => $channelId,
                    'post'    => $retryResult['post']['id'],
                ]);

                return [(string) $retryResult['post']['id']];
            }

            $message = (string) ($retryResult['message'] ?? $message);
        }

        if ($message !== '') {
            Log::warning('SocialPublisher/Buffer: createPost rejected', [
                'channel' => $channelId,
                'message' => $message,
                'text'    => mb_substr((string) ($input['text'] ?? ''), 0, 80).'…',
            ]);
        }

        return [];
    }

    /**
     * Returns the URL if it is reachable by external servers, or null for local dev URLs.
     * Buffer cannot reach localhost / *.test / *.local hosts.
     */
    private function publicUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');

        if ($host === ''
            || str_starts_with($host, 'localhost')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
        ) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws \RuntimeException on transport failure or GraphQL error.
     */
    private function graphql(string $query, array $variables = []): array
    {
        $token = (string) ($this->config['access_token'] ?? '');

        if ($token === '') {
            throw new \RuntimeException('Buffer access token is not set.');
        }

        $response = Http::timeout((int) ($this->config['timeout'] ?? 15))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
            ->withToken($token)
            ->post((string) ($this->config['base_url'] ?? 'https://api.buffer.com/graphql'), [
                'query'     => $query,
                'variables' => (object) $variables,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Buffer API error: '.$response->status().' — '.$response->body());
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            throw new \RuntimeException('Buffer GraphQL error: '.json_encode($json['errors']));
        }

        return $json['data'] ?? [];
    }
}
