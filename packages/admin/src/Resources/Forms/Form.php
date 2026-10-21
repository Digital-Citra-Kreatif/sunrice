<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;

/**
 * A resource's create and edit form: fields, optionally in sections.
 *
 * @api
 */
final class Form
{
    /** @var list<Component> */
    private array $schema = [];

    /** @param list<Component> $components */
    public function schema(array $components): self
    {
        $this->schema = $components;

        return $this;
    }

    /** @return list<Component> */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * The fields on the create or edit form.
     *
     * @return list<Field>
     */
    public function fields(string $operation): array
    {
        $fields = [];
        foreach ($this->schema as $component) {
            if (! $component->shownOn($operation)) {
                continue;
            }
            foreach ($component->fields() as $field) {
                if ($field->shownOn($operation)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * The form's sections for the page, fields outside a section in a first
     * untitled one: creating, or editing `$record`.
     *
     * @return list<array<string, mixed>>
     */
    public function toSections(string $operation, ?Model $record = null): array
    {
        $sections = [];
        $loose = [];
        foreach ($this->schema as $component) {
            if (! $component->shownOn($operation)) {
                continue;
            }
            $fields = array_values(array_filter($component->fields(), fn (Field $field) => $field->shownOn($operation)));
            if ($component instanceof Section) {
                $sections[] = [...$component->toArray($record), 'fields' => array_map(fn (Field $field) => $field->toArray($record), $fields)];
            } else {
                $loose = [...$loose, ...$fields];
            }
        }
        if ($loose !== []) {
            array_unshift($sections, ['kind' => 'section', 'title' => null, 'description' => null, 'columns' => 1, 'fields' => array_map(fn (Field $field) => $field->toArray($record), $loose)]);
        }

        return $sections;
    }
}
