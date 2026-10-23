<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Sunrice\Admin\Support\Say;

/**
 * Several choices from a list, as an array of the chosen values. Options
 * are fixed or come from a closure (called when the form is shown and
 * when it's saved); an array in place of a label is a group:
 *
 *     CheckboxList::make('tags')->options(['new' => 'New', 'sale' => 'On sale']);
 *     CheckboxList::make('permissions')->options(fn () => ['Shop' => ['shop.view' => 'View orders']]);
 *
 * Only the listed values are accepted. It isn't usually a column: write it
 * with ->saveUsing() and read it with ->loadUsing().
 *
 * With ->matrix() the values are dotted names ending in an action, like
 * permissions (`cms.entries.update`), and each group shows as a compact
 * table: a row per thing (`cms.entries`), a column per standard action
 * (View, Create, Edit, Publish, Moderate, Delete, Export), the rest
 * listed as short choices under the row, and `-own` variants
 * (`cms.entries.update-own`) on an "own only" line under the row, in
 * their action's column. Labels show as tooltips; ->shortLabels() gives
 * the other actions short labels, the full label staying their
 * description. On narrow screens it's the plain list. Only how it looks
 * changes.
 *
 * @api
 */
final class CheckboxList extends Field
{
    protected mixed $default = [];

    /** @var array<string|int, string|array<string|int, string>>|Closure(): array<string|int, string|array<string|int, string>> */
    private array|Closure $options = [];

    /** Its option labels are data, shown as written. */
    private bool $verbatim = false;

    private bool $matrix = false;

    /** @var array<string, string>|Closure(): array<string, string> */
    private array|Closure $shortLabels = [];

    /**
     * value => label, or group => [value => label].
     *
     * @param  array<string|int, string|array<string|int, string>>|Closure(): array<string|int, string|array<string|int, string>>  $options
     */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

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

    /** Each group as a table of things by action (see above), for permissions. */
    public function matrix(bool $matrix = true): self
    {
        $this->matrix = $matrix;

        return $this;
    }

    /**
     * Short labels for some options (value => label), shown where the full
     * label is too long (a matrix's other actions), the full label being
     * their description. Said in the panel's language unless verbatim.
     *
     * @param  array<string, string>|Closure(): array<string, string>  $labels
     */
    public function shortLabels(array|Closure $labels): self
    {
        $this->shortLabels = $labels;

        return $this;
    }

    public function type(): string
    {
        return 'checkboxes';
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(fn (array $option) => $option['value'], $this->resolve());
    }

    protected function typeRules(): array
    {
        return ['array'];
    }

    public function nestedRules(): array
    {
        return ["{$this->name}.*" => ['string', 'distinct', Rule::in($this->values())]];
    }

    public function formValue(?Model $record): mixed
    {
        $value = parent::formValue($record);
        $chosen = is_iterable($value) ? array_map(fn (mixed $v) => is_scalar($v) ? (string) $v : '', [...$value]) : [];

        return array_values(array_intersect($chosen, $this->values()));
    }

    protected function props(): array
    {
        return ['options' => $this->resolve()] + ($this->matrix ? ['matrix' => true] : []);
    }

    /** @return list<array{value: string, label: string, group: string|null, short?: string}>
     */
    private function resolve(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;
        $list = [];
        foreach ($options as $key => $item) {
            if (is_array($item)) {
                foreach ($item as $value => $label) {
                    $list[] = ['value' => (string) $value, 'label' => $this->verbatim ? $label : Say::it($label), 'group' => Say::it((string) $key)];
                }
            } else {
                $list[] = ['value' => (string) $key, 'label' => $this->verbatim ? $item : Say::it($item), 'group' => null];
            }
        }
        $shorts = $this->shortLabels instanceof Closure ? ($this->shortLabels)() : $this->shortLabels;
        if ($shorts !== []) {
            foreach ($list as $index => $option) {
                if (isset($shorts[$option['value']])) {
                    $list[$index]['short'] = $this->verbatim ? $shorts[$option['value']] : Say::it($shorts[$option['value']]);
                }
            }
        }

        return $list;
    }
}
