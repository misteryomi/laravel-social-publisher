# laravel-social-publisher

Driver-based social publishing for Laravel. Handles scheduling posts to Buffer (and future providers) with typed content DTOs and an AI-agnostic content layer.

Built for [Borderless](https://borderless.so) and [SponsorshipJobs](https://sponsorshipjobs.uk). Extracted as a package so both apps share one scheduling and content layer without duplicating code.

---

## What it does

- Schedules posts to Buffer via GraphQL (including Reels/short-video)
- Provides a `Platform` enum as the single source of truth for platform slugs, labels, and service identifiers
- Ships `SocialPost` and `CardCopy` value objects for typed, immutable post content
- Provides `SocialPostSchema` — a plain JSON Schema you pass to any LLM structured-output call
- Defines `SocialCopyGeneratorContract` so apps implement AI generation with their own LLM library — no AI dependencies in this package
- Includes a base `SocialQueueItem` Eloquent model you can extend with app-specific fields and relations
- Driver pattern via `Illuminate\Support\Manager` — swap or add providers without changing calling code

---

## Installation

```bash
composer require misteryomi/laravel-social-publisher
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=social-publisher-config
```

If you need the queue table (new apps only — skip if you already have a `social_queue_items` table):

```bash
php artisan vendor:publish --tag=social-publisher-migrations
php artisan migrate
```

---

## Configuration

`config/social-publisher.php` after publishing:

```php
return [
    'default' => env('SOCIAL_PUBLISHER_DRIVER', 'buffer'),

    'drivers' => [
        'buffer' => [
            'access_token'       => env('BUFFER_ACCESS_TOKEN'),
            'base_url'           => env('BUFFER_BASE_URL', 'https://api.buffer.com/graphql'),
            'timeout'            => 15,
            'connect_timeout'    => 5,
            'profiles_cache_ttl' => 300, // seconds

            // Profile IDs to use when calling queueToAll().
            // Get these from your Buffer dashboard (channel settings → profile ID).
            'default_profiles' => array_filter(explode(',', env('BUFFER_DEFAULT_PROFILES', ''))),
        ],
    ],
];
```

Required env vars:

```dotenv
BUFFER_ACCESS_TOKEN=your_token_here

# Optional — comma-separated profile IDs for queueToAll()
BUFFER_DEFAULT_PROFILES=abc123,def456
```

---

## Usage

### Scheduling a post directly

```php
use Misteryomi\SocialPublisher\Facades\SocialPublisher;

// Queue to specific profile IDs
SocialPublisher::queue('Hello world', ['profile_id_1', 'profile_id_2']);

// Queue with a scheduled time
SocialPublisher::queue('Hello world', ['profile_id_1'], scheduledAt: now()->addHours(2));

// Queue with an image
SocialPublisher::queue('Hello world', ['profile_id_1'], imageUrl: 'https://example.com/card.jpg');

// Queue to all profiles in default_profiles config
SocialPublisher::queueToAll('Hello world');
```

### Scheduling a Reel / short video

```php
SocialPublisher::queueReel(
    text: 'Caption here',
    profileIds: ['instagram_profile_id'],
    videoUrl: 'https://example.com/video.mp4',
    platform: 'instagram', // or 'tiktok'
);
```

### Listing connected profiles

```php
$profiles = SocialPublisher::profiles();
// [['id' => '...', 'service' => 'twitter', 'name' => 'MyHandle'], ...]
```

### Checking configuration

```php
if (SocialPublisher::isConfigured()) {
    // BUFFER_ACCESS_TOKEN is set and the driver can make calls
}
```

---

## The Platform enum

`Platform` is the single source of truth for platform slugs across the package and your app.

```php
use Misteryomi\SocialPublisher\Enums\Platform;

Platform::X->value;           // 'x'
Platform::X->label();         // 'X'
Platform::X->bufferService(); // 'twitter'  ← Buffer's internal name for X

Platform::LinkedIn->value;    // 'linkedin'
Platform::Instagram->value;   // 'instagram'
Platform::Facebook->value;    // 'facebook'
Platform::TikTok->value;      // 'tiktok'

// All platforms
Platform::cases(); // [Platform::X, Platform::LinkedIn, ...]

// Safe lookup from any string (DB values, API responses)
Platform::labelFor('x');        // 'X'
Platform::labelFor('linkedin'); // 'LinkedIn'
Platform::labelFor('unknown');  // 'Unknown' (ucfirst fallback)
```

The `bufferService()` distinction matters when looking up profile IDs from Buffer's API — Buffer calls X "twitter" internally, so use `$platform->bufferService()` for profile lookups and `$platform->value` for your own DB/config keys.

---

## Content layer

### SocialPost

A typed, immutable value object holding per-platform copy and a card block.

```php
use Misteryomi\SocialPublisher\Content\SocialPost;
use Misteryomi\SocialPublisher\Enums\Platform;

// Build from an LLM response array
$post = SocialPost::fromArray($raw);

// Get copy for a specific platform
$post->for(Platform::X);         // 'Check out these roles...'
$post->for(Platform::LinkedIn);  // 'This week on SponsorshipJobs...'

// Get by slug (handles 'twitter' → 'x' alias)
$post->forSlug('twitter'); // same as for(Platform::X)
$post->forSlug('x');

// Check if anything was generated
$post->isEmpty(); // true if all copy and card are empty

// Which platforms have copy
$post->platforms(); // ['x', 'linkedin', 'instagram']

// Add a card label (immutable — returns new instance)
$post = $post->withCardLabel('Jobs');

// Serialise back to array
$post->toArray();
// ['x' => '...', 'linkedin' => '...', 'card' => ['headline' => '...', ...]]
```

### CardCopy

The image card block: a headline with an optional highlighted phrase and subtitle.

```php
use Misteryomi\SocialPublisher\Content\CardCopy;

$card = new CardCopy(
    headline:  'Google is hiring with UK visa sponsorship',
    highlight: 'UK visa sponsorship',
    subtitle:  '12 roles open now',
    label:     'Jobs',
);

$card->isEmpty();    // false — headline is set
$card->toArray();    // array_filter — omits empty strings

// Fluent, immutable
$card = $card->withLabel('Spotlight');

// Build from an array (e.g. LLM response)
$card = CardCopy::fromArray($data);
```

### SocialPostSchema

Returns a plain JSON Schema (draft-07) for structured LLM output. Pass it to whatever LLM API you use — OpenAI, Anthropic, Gemini all accept this format.

```php
use Misteryomi\SocialPublisher\Content\SocialPostSchema;
use Misteryomi\SocialPublisher\Enums\Platform;

// Schema for all platforms + card block
$schema = SocialPostSchema::standard();

// Schema for a specific subset of platforms
$schema = SocialPostSchema::forPlatforms([Platform::X, Platform::LinkedIn]);
```

The returned schema includes per-platform character/format hints in the `description` fields so the LLM knows the constraints without your prompt having to enumerate them.

---

## Implementing AI content generation

The package defines `SocialCopyGeneratorContract` but has zero AI dependencies. You implement it in your app using your own LLM library.

```php
use Misteryomi\SocialPublisher\Contracts\SocialCopyGeneratorContract;
use Misteryomi\SocialPublisher\Content\SocialPost;
use Misteryomi\SocialPublisher\Content\SocialPostSchema;

class MyContentGenerator implements SocialCopyGeneratorContract
{
    public function __construct(private readonly MyLlmClient $llm) {}

    public function generate(array $context): SocialPost
    {
        $prompt = $this->buildPrompt($context);

        $raw = $this->llm->structured($prompt, SocialPostSchema::standard());

        return SocialPost::fromArray($raw);
    }

    private function buildPrompt(array $context): string
    {
        return "Write social posts about: {$context['topic']}. No hype, no emoji.";
    }
}
```

Then bind it in your `AppServiceProvider`:

```php
$this->app->bind(SocialCopyGeneratorContract::class, MyContentGenerator::class);
```

The `$context` array is entirely up to your app — it might be a list of jobs, a policy update string, a topic slug, or any other structured input. The contract just requires you return a `SocialPost`.

---

## Publishing posts with card images

If you render branded card images alongside posts, the typical flow is:

```php
use Misteryomi\SocialPublisher\Enums\Platform;
use Misteryomi\SocialPublisher\Facades\SocialPublisher;

foreach (Platform::cases() as $platform) {
    $profileId = config("social.profiles.{$platform->bufferService()}")
              ?? config("social.profiles.{$platform->value}");

    $text = $post->for($platform);

    if (! $profileId || ! $text) {
        continue;
    }

    // Render a card image and get a public URL (your app's responsibility)
    $imageUrl = $this->renderCard($post->card->toArray(), $platform);

    SocialPublisher::queue($text, [$profileId], $scheduledAt, $imageUrl, $platform->value);
}
```

Profile config is keyed by Buffer service name (`'twitter'` for X) because that is what Buffer's API returns when you call `profiles()`. Use `$platform->bufferService()` as the lookup key with `$platform->value` as a fallback.

---

## The queue model

`SocialQueueItem` is an Eloquent base model for drafting, approving, and tracking posts before they go to Buffer.

```php
use Misteryomi\SocialPublisher\Models\SocialQueueItem;

// Status constants
SocialQueueItem::STATUS_DRAFT
SocialQueueItem::STATUS_APPROVED
SocialQueueItem::STATUS_QUEUED
SocialQueueItem::STATUS_PUBLISHED
SocialQueueItem::STATUS_DISMISSED

// Scopes
SocialQueueItem::draft()->get();
SocialQueueItem::actionable()->get();           // draft + approved
SocialQueueItem::forPlatform('linkedin')->get();
SocialQueueItem::forSource('policy_update')->get();
```

### Extending in your app

```php
use Misteryomi\SocialPublisher\Models\SocialQueueItem as BaseQueueItem;

class SocialQueueItem extends BaseQueueItem
{
    // Add app-specific source constants
    public const SOURCE_POLICY   = 'policy_update';
    public const SOURCE_CAMPAIGN = 'campaign';

    // Spread the base fillable and add your own columns
    protected $fillable = [...parent::FILLABLE_BASE, 'assistant_run_id', 'campaign_id'];

    // Human label for the source — called in admin UIs
    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_POLICY   => 'Policy Update',
            self::SOURCE_CAMPAIGN => 'Campaign',
            default               => parent::sourceLabel(),
        };
    }

    // App-specific relations
    public function assistantRun(): BelongsTo
    {
        return $this->belongsTo(AssistantRun::class);
    }
}
```

The base table schema (from the published migration) covers: `uuid`, `source`, `source_id`, `platform`, `content_type`, `copy` (text), `card_config` (JSON), `card_image_url`, `status`, `buffer_post_id`, `suggested_timing`, `scheduled_at`, `queued_at`, `published_at`. Add app-specific columns in your own migration rather than modifying the package migration.

---

## Adding a custom driver

Use `SocialPublisher::extend()` in your `AppServiceProvider::boot()`:

```php
use Misteryomi\SocialPublisher\Facades\SocialPublisher;

SocialPublisher::extend('hootsuite', function ($app) {
    return new HootsuiteDriver(config('social-publisher.drivers.hootsuite'));
});
```

Your driver must implement `SocialDriverContract`:

```php
use Misteryomi\SocialPublisher\Contracts\SocialDriverContract;

class HootsuiteDriver implements SocialDriverContract
{
    public function driverName(): string { return 'Hootsuite'; }
    public function isConfigured(): bool { return (bool) $this->config['api_key']; }
    public function profiles(): array { ... }
    public function profileIdForPlatform(string $platform): ?string { ... }
    public function queue(string $text, array $profileIds, ...): array { ... }
    public function queueReel(string $text, array $profileIds, string $videoUrl, ...): array { ... }
}
```

Then set `SOCIAL_PUBLISHER_DRIVER=hootsuite` and add a `drivers.hootsuite` block to your published config.

---

## Notes

- **localhost / *.test / *.local image URLs are silently skipped** by the Buffer driver — it can't fetch them. Use a tunnel (ngrok, Expose) if you need to test card image attachment locally.
- Buffer profile IDs are cached for `profiles_cache_ttl` seconds (default 5 minutes). Clear the `social_publisher_buffer_profiles` cache key after connecting new channels.
- Instagram Reels use Buffer's `REEL` mediaType; TikTok uses `VIDEO`. Both are handled by `queueReel()`.
- The package has no `illuminate/queue` dependency — job dispatch is the app's responsibility.
