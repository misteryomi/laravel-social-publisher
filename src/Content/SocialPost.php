<?php

namespace Misteryomi\SocialPublisher\Content;

use Misteryomi\SocialPublisher\Enums\Platform;

/**
 * A fully generated social post: per-platform copy + one shared card block.
 *
 * Built from the structured output of an LLM call, then handed to
 * SocialCardPublisher (or equivalent app code) for rendering and scheduling.
 */
final class SocialPost
{
    /** @param array<string, string> $platformCopy Platform slug => post text. */
    public function __construct(
        private readonly array    $platformCopy,
        public readonly CardCopy  $card,
    ) {}

    /**
     * Build from the raw array an LLM returns for the standard schema.
     * Tolerates missing keys gracefully.
     */
    public static function fromArray(array $data): self
    {
        $card = isset($data['card']) && is_array($data['card'])
            ? CardCopy::fromArray($data['card'])
            : new CardCopy('');

        $copy = [];
        foreach (Platform::cases() as $platform) {
            $value = $data[$platform->value] ?? null;
            if (is_string($value) && $value !== '') {
                $copy[$platform->value] = $value;
            }
        }

        return new self($copy, $card);
    }

    /** Copy text for a specific platform, or empty string if not provided. */
    public function for(Platform $platform): string
    {
        return $this->platformCopy[$platform->value] ?? '';
    }

    /**
     * Copy text by raw slug — useful when iterating over config-driven platform lists.
     * Handles the 'twitter' → 'x' alias transparently.
     */
    public function forSlug(string $slug): string
    {
        // 'twitter' is Buffer's service name for Platform::X; normalise on lookup.
        $normalized = $slug === 'twitter' ? 'x' : $slug;

        return $this->platformCopy[$normalized] ?? $this->platformCopy[$slug] ?? '';
    }

    /** Whether this post has any copy at all. */
    public function isEmpty(): bool
    {
        return $this->platformCopy === [] && $this->card->isEmpty();
    }

    /** All platforms that have copy in this post. */
    public function platforms(): array
    {
        return array_keys($this->platformCopy);
    }

    /** Return a new instance with a card label set — fluent, immutable. */
    public function withCardLabel(string $label): self
    {
        return new self($this->platformCopy, $this->card->withLabel($label));
    }

    public function toArray(): array
    {
        return array_merge($this->platformCopy, ['card' => $this->card->toArray()]);
    }
}
