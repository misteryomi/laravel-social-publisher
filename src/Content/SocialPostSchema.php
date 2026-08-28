<?php

namespace Misteryomi\SocialPublisher\Content;

use Misteryomi\SocialPublisher\Enums\Platform;

/**
 * Standard JSON schema for LLM structured output.
 *
 * Pass SocialPostSchema::standard() as the schema argument to your LLM call.
 * The returned array is provider-agnostic — it is plain JSON Schema draft-07,
 * which every major structured-output API (OpenAI, Anthropic, Gemini) accepts.
 *
 * Usage:
 *   $schema = SocialPostSchema::standard();
 *   $raw    = $llm->structured($prompt, $schema);   // your app's LLM call
 *   $post   = SocialPost::fromArray($raw);
 */
final class SocialPostSchema
{
    /**
     * Full schema: per-platform copy for every known platform + card block.
     *
     * @return array<string, mixed>
     */
    public static function standard(): array
    {
        return [
            'type'       => 'object',
            'required'   => [...array_map(fn ($p) => $p->value, Platform::cases()), 'card'],
            'properties' => array_merge(
                self::platformProperties(),
                ['card' => self::cardProperty()],
            ),
        ];
    }

    /**
     * Schema for a subset of platforms — useful when you only post to some.
     *
     * @param  Platform[]  $platforms
     * @return array<string, mixed>
     */
    public static function forPlatforms(array $platforms): array
    {
        $props = [];
        foreach ($platforms as $p) {
            $props[$p->value] = self::platformCopyProperty($p);
        }

        return [
            'type'       => 'object',
            'required'   => [...array_map(fn ($p) => $p->value, $platforms), 'card'],
            'properties' => array_merge($props, ['card' => self::cardProperty()]),
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function platformProperties(): array
    {
        $props = [];
        foreach (Platform::cases() as $platform) {
            $props[$platform->value] = self::platformCopyProperty($platform);
        }

        return $props;
    }

    private static function platformCopyProperty(Platform $platform): array
    {
        $hints = [
            Platform::X->value         => 'Max 280 characters including any URL. Punchy, 2-3 relevant hashtags only.',
            Platform::LinkedIn->value  => '2-3 short paragraphs. Professional tone. Max 2 hashtags. End with a clear CTA.',
            Platform::Facebook->value  => 'Casual, accessible tone. End with an open question to drive engagement.',
            Platform::Instagram->value => 'One sentence naming one company/topic, then 10 relevant hashtags on a new line. No lists.',
            Platform::TikTok->value    => 'Short, punchy caption. 3-5 hashtags. Hook in the first line.',
        ];

        return [
            'type'        => 'string',
            'description' => $hints[$platform->value] ?? "Copy for {$platform->label()}.",
        ];
    }

    private static function cardProperty(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['headline', 'highlight', 'subtitle'],
            'properties' => [
                'headline'  => [
                    'type'        => 'string',
                    'description' => 'Large image headline. Max 9 words, sentence case, no trailing punctuation.',
                ],
                'highlight' => [
                    'type'        => 'string',
                    'description' => '1-3 word phrase taken verbatim from the headline to accent with colour. Must appear in the headline exactly.',
                ],
                'subtitle'  => [
                    'type'        => 'string',
                    'description' => 'One short supporting line under the headline. Max 12 words.',
                ],
            ],
        ];
    }
}
