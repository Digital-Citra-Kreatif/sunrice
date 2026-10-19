<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\RegisteredSettings;

/**
 * Validates a settings section's form input and saves it (SaveSettings):
 * only the form's fields; an empty secret keeps its value. The Settings
 * page and the AI tools use it. Authorize first.
 *
 * @internal
 */
final class UpdateSettings
{
    public function __construct(private SaveSettings $save) {}

    /**
     * @param  array<mixed>  $input
     * @return list<string> the settings that changed
     *
     * @throws ValidationException
     */
    public function handle(RegisteredSettings $section, array $input): array
    {
        $settings = $section->load();
        $current = $settings->toArray();
        $fields = array_values(array_filter($section->fields(), fn (Field $field) => ! $field->isDisabled(null)));

        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            $fieldRules = $field->validationRules(null);
            // A secret that is already set may be left empty.
            if ($field->keepsValueWhenEmpty() && $current[$field->name] !== null && $current[$field->name] !== '') {
                $fieldRules = array_map(fn (string|object $rule) => $rule === 'required' ? 'nullable' : $rule, $fieldRules);
            }
            $rules[$field->name] = $fieldRules;
            $attributes[$field->name] = $field->getLabel();
            foreach ($field->nestedRules() as $path => $nested) {
                $rules[$path] = $nested;
                $attributes[$path] = $field->getLabel();
            }
        }
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($input, $rules, [], $attributes)->validate();

        // Only the form's fields are written; empty secrets keep their value.
        $values = [];
        foreach ($fields as $field) {
            $value = $validated[$field->name] ?? null;
            if ((! array_key_exists($field->name, $validated) && ! $field instanceof Toggle) || ($field->keepsValueWhenEmpty() && ($value === null || $value === ''))) {
                continue;
            }
            $values[$field->name] = $field instanceof Toggle ? (bool) $value : $value;
        }

        return $this->save->handle($settings, $values);
    }
}
