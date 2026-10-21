<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Sunrice\Admin\Support\Say;

/**
 * A link on a resource form: in a section's header (Section::links()) or
 * under a field (Field::links()). `{name}` in its address is the form's
 * current value of that field, so it follows what's chosen:
 *
 *     Link::make('Edit', url('/admin/cms/blueprints/{blueprint}/edit'))->showWhen('blueprint'),
 *     Link::make('View on site', $record->url)->newTab(),
 *
 * showWhen() and showUnless() follow the form as it's edited, like a
 * field's showWhen().
 *
 * @api
 */
final class Link
{
    private bool $newTab = false;

    /** @var array{field: string, value: string|int|bool|null, not: bool}|null */
    private ?array $showWhen = null;

    private function __construct(private readonly string $label, private readonly string $url) {}

    public static function make(string $label, string $url): self
    {
        return new self($label, $url);
    }

    /** Opens in a new tab (marked ↗), for pages outside the panel. */
    public function newTab(bool $newTab = true): self
    {
        $this->newTab = $newTab;

        return $this;
    }

    /** Shown only while another field is filled in, or holds `$value`. */
    public function showWhen(string $field, string|int|bool|null $value = null): self
    {
        $this->showWhen = ['field' => $field, 'value' => $value, 'not' => false];

        return $this;
    }

    /** Shown only while another field is empty (or doesn't hold `$value`). */
    public function showUnless(string $field, string|int|bool|null $value = null): self
    {
        $this->showWhen = ['field' => $field, 'value' => $value, 'not' => true];

        return $this;
    }

    /** @return array{label: string, url: string, newTab: bool, showWhen: array{field: string, value: string|int|bool|null, not: bool}|null} */
    public function toArray(): array
    {
        return ['label' => Say::it($this->label), 'url' => $this->url, 'newTab' => $this->newTab, 'showWhen' => $this->showWhen];
    }
}
