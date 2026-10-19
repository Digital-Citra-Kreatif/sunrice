<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\RegisteredResource;

/**
 * Validates a resource's form input and writes it to the record: only the
 * form's (enabled) fields, in a transaction. The screens and the AI tools
 * use it, so both validate and write the same way. Authorize first.
 *
 * @internal
 */
final class SaveRecord
{
    /**
     * @param  array<mixed>  $input  the form's values (anything else is ignored)
     * @param  'create'|'edit'  $operation
     * @return list<string> the fields that changed
     *
     * @throws ValidationException
     */
    public function handle(RegisteredResource $resource, Model $record, array $input, string $operation): array
    {
        // Disabled fields are shown, never validated or written.
        $fields = array_values(array_filter(
            $resource->form()->fields($operation),
            fn (Field $field) => ! $field->isDisabled($record->exists ? $record : null),
        ));
        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            $rules[$field->name] = $field->validationRules($record);
            $attributes[$field->name] = $field->getLabel();
            foreach ($field->nestedRules() as $path => $nested) {
                $rules[$path] = $nested;
                $attributes[$path] = $field->getLabel();
            }
        }
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($input, $rules, [], $attributes)->validate();

        // Only declared fields are written, whatever else the request sends.
        $present = array_values(array_filter($fields, fn (Field $field) => array_key_exists($field->name, $validated) || $field instanceof Toggle));
        $before = $record->exists ? array_map(fn (Field $field) => json_encode($field->formValue($record)), $present) : [];
        DB::transaction(function () use ($record, $present, $validated): void {
            foreach ($present as $field) {
                if (! $field->isSavedAfterRecord()) {
                    $field->fill($record, $validated[$field->name] ?? null);
                }
            }
            $record->save();
            foreach ($present as $field) {
                if ($field->isSavedAfterRecord()) {
                    $field->saveAfterRecord($record, $validated[$field->name] ?? null);
                }
            }
        });

        // Values aren't logged, only which fields changed; a new password or
        // secret counts as a change.
        $changed = [];
        foreach ($present as $index => $field) {
            $value = $validated[$field->name] ?? null;
            if ($field->keepsValueWhenEmpty() ? ($value !== null && $value !== '') : ($before[$index] ?? null) !== json_encode($field->formValue($record))) {
                $changed[] = $field->name;
            }
        }

        return $changed;
    }
}
