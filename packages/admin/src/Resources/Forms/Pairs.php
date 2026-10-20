<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

/**
 * A list of key and value pairs, kept as text, one "key: value" per line,
 * and edited as rows: add, remove, move up and down.
 *
 *     Pairs::make('languages')->labels('Code', 'Name')
 *         ->suggest(['en' => 'English', 'id' => 'Indonesian'])->firstBadge('Main')
 *
 * Picking a suggested key fills an empty value with its suggestion. With
 * ->extra('Address') each row has a third, optional column, kept after a
 * bar: "id: Bahasa Indonesia | indonesia".
 *
 * @api
 */
final class Pairs extends Field
{
    private string $keyLabel = 'Key';

    private string $valueLabel = 'Value';

    /** @var array<string, string> */
    private array $suggestions = [];

    private ?string $firstBadge = null;

    private string $addLabel = 'Add';

    private int $maxLength = 5000;

    /** @var array{label: string, placeholder: string|null, firstPlaceholder: string|null}|null */
    private ?array $extra = null;

    public function type(): string
    {
        return 'pairs';
    }

    public function labels(string $key, string $value): self
    {
        $this->keyLabel = $key;
        $this->valueLabel = $value;

        return $this;
    }

    /** @param array<string, string> $suggestions key => its usual value */
    public function suggest(array $suggestions): self
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    /** A tag on the first row, when the first one counts most ("Main"). */
    public function firstBadge(?string $badge): self
    {
        $this->firstBadge = $badge;

        return $this;
    }

    /**
     * A third, optional column. Its placeholder may name the row's key,
     * "/{key}"; the first row may have its own.
     */
    public function extra(string $label, ?string $placeholder = null, ?string $firstPlaceholder = null): self
    {
        $this->extra = ['label' => $label, 'placeholder' => $placeholder, 'firstPlaceholder' => $firstPlaceholder];

        return $this;
    }

    public function addLabel(string $label): self
    {
        $this->addLabel = $label;

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}"];
    }

    protected function props(): array
    {
        return [
            'keyLabel' => $this->keyLabel,
            'valueLabel' => $this->valueLabel,
            'suggestions' => array_map(fn (string $key, string $value): array => ['key' => $key, 'value' => $value], array_keys($this->suggestions), $this->suggestions),
            'firstBadge' => $this->firstBadge,
            'addLabel' => $this->addLabel,
            'extra' => $this->extra,
        ];
    }
}
