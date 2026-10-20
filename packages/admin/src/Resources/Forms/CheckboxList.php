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
 * @api
 */
final class CheckboxList extends Field
{
    protected mixed $default = [];

    /** @var array<string|int, string|array<string|int, string>>|Closure(): array<string|int, string|array<string|int, string>> */
    private array|Closure $options = [];

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
        return ['options' => $this->resolve()];
    }

    /** @return list<array{value: string, label: string, group: string|null}>
     */
    private function resolve(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;
        $list = [];
        foreach ($options as $key => $item) {
            if (is_array($item)) {
                foreach ($item as $value => $label) {
                    $list[] = ['value' => (string) $value, 'label' => Say::it($label), 'group' => Say::it((string) $key)];
                }
            } else {
                $list[] = ['value' => (string) $key, 'label' => Say::it($item), 'group' => null];
            }
        }

        return $list;
    }
}
