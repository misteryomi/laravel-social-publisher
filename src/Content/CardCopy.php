<?php

namespace Misteryomi\SocialPublisher\Content;

/**
 * The image card copy block: headline with an optional highlighted phrase
 * and a short supporting subtitle line.
 */
final class CardCopy
{
    public function __construct(
        public readonly string $headline,
        public readonly string $highlight = '',
        public readonly string $subtitle = '',
        public readonly string $label = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            headline:  (string) ($data['headline']  ?? ''),
            highlight: (string) ($data['highlight'] ?? ''),
            subtitle:  (string) ($data['subtitle']  ?? ''),
            label:     (string) ($data['label']     ?? ''),
        );
    }

    public function withLabel(string $label): self
    {
        return new self($this->headline, $this->highlight, $this->subtitle, $label);
    }

    public function toArray(): array
    {
        return array_filter([
            'headline'  => $this->headline,
            'highlight' => $this->highlight,
            'subtitle'  => $this->subtitle,
            'label'     => $this->label,
        ], fn ($v) => $v !== '');
    }

    public function isEmpty(): bool
    {
        return $this->headline === '';
    }
}
