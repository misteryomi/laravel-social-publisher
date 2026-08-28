<?php

namespace Misteryomi\SocialPublisher\Enums;

enum Platform: string
{
    case X         = 'x';
    case LinkedIn  = 'linkedin';
    case Instagram = 'instagram';
    case Facebook  = 'facebook';
    case TikTok    = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::X         => 'X',
            self::LinkedIn  => 'LinkedIn',
            self::Instagram => 'Instagram',
            self::Facebook  => 'Facebook',
            self::TikTok    => 'TikTok',
        };
    }

    /**
     * The service identifier Buffer uses for this platform.
     * Most match the slug; X is the exception ('twitter').
     */
    public function bufferService(): string
    {
        return match ($this) {
            self::X => 'twitter',
            default => $this->value,
        };
    }

    /**
     * Resolve from any string, returning null for unknown slugs.
     * Safe to use on untrusted input (e.g. DB values, API responses).
     */
    public static function labelFor(string $slug): string
    {
        return self::tryFrom($slug)?->label() ?? ucfirst($slug);
    }
}
