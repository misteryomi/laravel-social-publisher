<?php

namespace Misteryomi\SocialPublisher\Facades;

use Misteryomi\SocialPublisher\SocialPublisherManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Misteryomi\SocialPublisher\Contracts\SocialDriverContract driver(?string $driver = null)
 * @method static string   driverName()
 * @method static bool     isConfigured()
 * @method static array    profiles()
 * @method static string|null profileIdForPlatform(string $platform)
 * @method static array    queue(string $text, array $profileIds, ?\Carbon\CarbonInterface $scheduledAt = null, ?string $imageUrl = null, ?string $platform = null)
 * @method static array    queueReel(string $text, array $profileIds, string $videoUrl, ?\Carbon\CarbonInterface $scheduledAt = null, ?string $platform = null)
 * @method static array    queueToAll(string $text, ?\Carbon\CarbonInterface $scheduledAt = null, ?string $imageUrl = null)
 * @method static void     extend(string $driver, \Closure $callback)
 *
 * @see \Misteryomi\SocialPublisher\SocialPublisherManager
 */
class SocialPublisher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SocialPublisherManager::class;
    }
}
