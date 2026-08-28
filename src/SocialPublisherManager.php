<?php

namespace Misteryomi\SocialPublisher;

use Carbon\CarbonInterface;
use Misteryomi\SocialPublisher\Contracts\SocialDriverContract;
use Misteryomi\SocialPublisher\Drivers\BufferDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;

/**
 * Driver manager for social publishing.
 *
 * Usage:
 *   app(SocialPublisherManager::class)->queue(...)       // default driver
 *   app(SocialPublisherManager::class)->driver('buffer') // explicit
 *   SocialPublisher::queue(...)                          // via facade
 *
 * Add custom drivers at boot time:
 *   SocialPublisher::extend('hootsuite', fn ($app) => new HootsuiteDriver([...]));
 *
 * @mixin SocialDriverContract
 */
class SocialPublisherManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) config('social-publisher.default', 'buffer');
    }

    protected function createBufferDriver(): BufferDriver
    {
        return new BufferDriver(
            config('social-publisher.drivers.buffer', []),
        );
    }

    /**
     * Queue a post to all profiles listed in `social-publisher.drivers.{driver}.default_profiles`.
     *
     * @return array<int, string>
     */
    public function queueToAll(
        string $text,
        ?CarbonInterface $scheduledAt = null,
        ?string $imageUrl = null,
    ): array {
        $profiles = $this->defaultProfiles();

        if ($profiles === []) {
            Log::warning('SocialPublisher: no default profiles configured — skipping post');

            return [];
        }

        return $this->queue($text, $profiles, $scheduledAt, $imageUrl);
    }

    /**
     * Whether the active driver has the credentials it needs.
     */
    public function isConfigured(): bool
    {
        try {
            return $this->driver()->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Human-readable name for the active driver.
     */
    public function driverName(): string
    {
        try {
            return $this->driver()->driverName();
        } catch (\Throwable) {
            return 'Social';
        }
    }

    /** @return array<int, string> */
    private function defaultProfiles(): array
    {
        $driver = $this->getDefaultDriver();

        return array_values(array_filter(
            (array) config("social-publisher.drivers.{$driver}.default_profiles", []),
        ));
    }
}
