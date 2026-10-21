<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Sunrice\Admin\Support\Say;

/**
 * A list of key and value pairs, kept as text, one "key: value" per line,
 * and edited as rows: add, remove, move up and down.
 *
 *     Pairs::make('languages')->labels('Code', 'Name')
 *         ->suggest(['en' => 'English', 'id' => 'Indonesian'])->firstBadge('Main')
 *
 * Suggested keys are offered in a searchable list (by key or value), and
 * any other key can still be typed; picking one fills an empty value with
 * its suggestion. ->hints('en, id…', …) puts a short hint under each
 * column's heading. With
 * ->extra('Address') each row has a third, optional column, kept after a
 * bar: "id: Bahasa Indonesia | indonesia".
 *
 * With ->fixed() the keys are given: one labelled input per key, nothing
 * to add, remove or reorder, still kept as "key: value" lines (lines of
 * other keys stay as they are):
 *
 *     Pairs::make('descriptions')->fixed(['id' => 'Bahasa Indonesia', 'fr' => 'Français'])
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

    /** @var array<string, string>|null key => its input's label */
    private ?array $fixed = null;

    /** @var array{key: string|null, value: string|null, extra: string|null} */
    private array $hints = ['key' => null, 'value' => null, 'extra' => null];

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

    /**
     * The keys, each with its own labelled input (key => label), instead of
     * rows to add and remove.
     *
     * @param  array<string, string>  $keys
     */
    public function fixed(array $keys): self
    {
        $this->fixed = $keys;

        return $this;
    }

    /** A short hint under each column's heading (the key's, the value's, the third column's). */
    public function hints(?string $key = null, ?string $value = null, ?string $extra = null): self
    {
        $this->hints = ['key' => $key, 'value' => $value, 'extra' => $extra];

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
            'keyLabel' => Say::it($this->keyLabel),
            'valueLabel' => Say::it($this->valueLabel),
            'suggestions' => array_map(fn (string $key, string $value): array => ['key' => $key, 'value' => $value], array_keys($this->suggestions), $this->suggestions),
            'firstBadge' => Say::it($this->firstBadge),
            'addLabel' => Say::it($this->addLabel),
            'extra' => $this->extra === null ? null : array_map(fn (?string $text): ?string => Say::it($text), $this->extra),
            'hints' => array_map(fn (?string $text): ?string => Say::it($text), $this->hints),
            'fixed' => $this->fixed === null ? null : array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => Say::it($label)], array_keys($this->fixed), $this->fixed),
        ];
    }
}
