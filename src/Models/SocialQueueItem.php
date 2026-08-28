<?php

namespace Misteryomi\SocialPublisher\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base model for the social_queue_items table.
 *
 * Extend this in your app to add source constants, relations, and
 * app-specific sourceLabel() / hasCard() overrides:
 *
 *   class SocialQueueItem extends \Misteryomi\SocialPublisher\Models\SocialQueueItem
 *   {
 *       public const SOURCE_POLICY = 'policy_update';
 *       public function sourceLabel(): string { ... }
 *   }
 */
class SocialQueueItem extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DISMISSED = 'dismissed';

    /** Base fillable columns — apps can spread this and append their own. */
    public const FILLABLE_BASE = [
        'uuid',
        'source',
        'source_id',
        'platform',
        'content_type',
        'copy',
        'card_config',
        'card_image_url',
        'status',
        'buffer_post_id',
        'suggested_timing',
        'scheduled_at',
        'queued_at',
        'published_at',
    ];

    public const PLATFORM_LABELS = [
        'x'         => 'X',
        'linkedin'  => 'LinkedIn',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'tiktok'    => 'TikTok',
    ];

    protected $fillable = self::FILLABLE_BASE;

    protected $casts = [
        'card_config'  => 'array',
        'scheduled_at' => 'datetime',
        'queued_at'    => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }

    /** Override in the app model for domain-specific logic. */
    public function hasCard(): bool
    {
        return ! empty($this->card_config);
    }

    /** Override in the app model to return a human label for the source. */
    public function sourceLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->source ?? ''));
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_APPROVED]);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeForSource($query, string $source)
    {
        return $query->where('source', $source);
    }
}
