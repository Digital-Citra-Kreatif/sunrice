<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Sunrice\Admin\Support\Say;

/**
 * A choice from fixed options: ->options(['draft' => 'Draft', 'live' => 'Live']),
 * or a closure returning them (called when the form is shown and when it's
 * saved, so building the form runs no query), which gets the record being
 * edited (null when creating): ->options(fn (?Model $record) => …). Only
 * the listed values are accepted. ->searchable() adds a search box over
 * the options, for long lists.
 *
 * Options may be grouped under headings: an array as a value is a group,
 * ['Shops' => ['Store' => 'Store', 'Bakery' => 'Bakery'], …]. With
 * ->other() the list ends with "Other…", which shows a text input for a
 * value that isn't listed, checked by the rules given; a saved value
 * that isn't an option shows there.
 *
 * @api
 */
final class Select extends Field
{
    /** @var array<string|int, string|array<string|int, string>>|Closure(?Model): array<string|int, string|array<string|int, string>> */
    private array|Closure $options = [];

    /** @var array{label: string, placeholder: string|null, rules: list<string|object>}|null */
    private ?array $other = null;

    private bool $searchable = false;

    /** Its option labels are data, shown as written. */
    private bool $verbatim = false;

    /** The record the form is shown or saved for, for an options closure. */
    private ?Model $record = null;

    /** In an action's dialog: the records it runs on aren't offered. */
    private bool $withoutTargets = false;

    /** @param array<string|int, string|array<string|int, string>>|Closure(?Model): array<string|int, string|array<string|int, string>> $options value => label, or group => [value => label] */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Ends the list with "Other…" (or `$label`), which shows a text input
     * for any value besides the options, accepted when it passes `$rules`:
     * ->other('Other…', ['regex:/^[A-Z][A-Za-z]+$/'], 'Brewery'). The value
     * kept is what's typed.
     *
     * @param  list<string|object>  $rules
     */
    public function other(string $label = 'Other…', array $rules = [], ?string $placeholder = null): self
    {
        $this->other = ['label' => $label, 'placeholder' => $placeholder, 'rules' => $rules];

        return $this;
    }

    /** A search box over the options (they're all sent, filtered as one types). */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Shows the option labels as written instead of in the panel's
     * language: for options that are data (blueprint titles, roles).
     */
    public function verbatim(bool $verbatim = true): self
    {
        $this->verbatim = $verbatim;

        return $this;
    }

    /**
     * In an action's dialog, leaves out the options whose value is the key
     * of a record the action runs on (a Delete dialog's "Transfer to"). The
     * handler still gets to refuse one, for input sent by hand.
     */
    public function withoutTargets(bool $without = true): self
    {
        $this->withoutTargets = $without;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    public function toArray(?Model $record = null): array
    {
        $this->record = $record;

        return parent::toArray($record);
    }

    public function validationRules(?Model $record): array
    {
        $this->record = $record;

        return parent::validationRules($record);
    }

    protected function typeRules(): array
    {
        $values = array_map('strval', array_keys($this->flat()));
        if ($this->other === null) {
            return [Rule::in($values)];
        }
        $rules = $this->other['rules'];

        // A listed value, or another that passes the "Other…" rules.
        return ['string', function (string $attribute, mixed $value, Closure $fail) use ($values, $rules): void {
            if (in_array((string) (is_scalar($value) ? $value : ''), $values, true)) {
                return;
            }
            $validator = Validator::make([$attribute => $value], [$attribute => $rules], [], [$attribute => Say::it($this->label)]);
            if ($validator->fails()) {
                $fail((string) $validator->errors()->first($attribute));
            }
        }];
    }

    protected function props(): array
    {
        $options = [];
        foreach ($this->resolve() as $value => $label) {
            if (is_array($label)) {
                $group = $this->verbatim ? (string) $value : Say::it((string) $value);
                foreach ($label as $inner => $text) {
                    $options[] = ['value' => (string) $inner, 'label' => $this->verbatim ? $text : Say::it($text), 'group' => $group];
                }
            } else {
                $options[] = ['value' => (string) $value, 'label' => $this->verbatim ? $label : Say::it($label)];
            }
        }

        return ['options' => $options]
            + ($this->searchable ? ['searchable' => true] : [])
            + ($this->withoutTargets ? ['withoutTargets' => true] : [])
            + ($this->other !== null ? ['other' => ['label' => Say::it($this->other['label']), 'placeholder' => Say::it($this->other['placeholder'])]] : []);
    }

    /** @return array<string|int, string|array<string|int, string>> */
    private function resolve(): array
    {
        return $this->options instanceof Closure ? ($this->options)($this->record?->exists === true ? $this->record : null) : $this->options;
    }

    /** @return array<string|int, string> the options without their groups */
    private function flat(): array
    {
        $flat = [];
        foreach ($this->resolve() as $value => $label) {
            if (is_array($label)) {
                $flat += $label;
            } else {
                $flat[$value] = $label;
            }
        }

        return $flat;
    }
}
