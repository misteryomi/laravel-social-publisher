<?php

namespace Misteryomi\SocialPublisher\Contracts;

use Carbon\CarbonInterface;

interface SocialDriverContract
{
    /**
     * Human-readable driver name for UI labels (e.g. "Buffer").
     */
    public function driverName(): string;

    /**
     * Whether the driver has the credentials it needs to make API calls.
     */
    public function isConfigured(): bool;

    /**
     * Connected profiles/channels for the authenticated account.
     *
     * Each entry: ['id' => string, 'service' => string, 'name' => string]
     * Service values match the platform's own identifiers: 'twitter', 'linkedin',
     * 'instagram', 'facebook', 'tiktok', etc.
     *
     * @return array<int, array{id: string, service: string, name: string}>
     */
    public function profiles(): array;

    /**
     * Map a generic platform name to a profile ID from the connected accounts.
     * Returns null when no connected profile matches.
     *
     * Canonical platform names: 'x', 'linkedin', 'instagram', 'facebook', 'tiktok'.
     */
    public function profileIdForPlatform(string $platform): ?string;

    /**
     * Queue an image post to the given profile IDs.
     *
     * Returns driver-specific post IDs on success, or an empty array on failure.
     * Failures are logged; they do not throw.
     *
     * @param  array<int, string>  $profileIds
     * @param  string|null         $imageUrl    Publicly reachable URL — local dev URLs are silently skipped.
     * @param  string|null         $platform    Platform hint for provider-specific quirks.
     * @return array<int, string>
     */
    public function queue(
        string $text,
        array $profileIds,
        ?CarbonInterface $scheduledAt = null,
        ?string $imageUrl = null,
        ?string $platform = null,
    ): array;

    /**
     * Queue a Reel / short-video post to the given profile IDs.
     *
     * Uses the platform's native video format (REEL for Instagram, VIDEO for TikTok).
     * Returns driver-specific post IDs on success, or an empty array on failure.
     *
     * @param  array<int, string>  $profileIds
     * @param  string|null         $platform    'instagram', 'tiktok', or null (defaults to REEL).
     * @return array<int, string>
     */
    public function queueReel(
        string $text,
        array $profileIds,
        string $videoUrl,
        ?CarbonInterface $scheduledAt = null,
        ?string $platform = null,
    ): array;
}
