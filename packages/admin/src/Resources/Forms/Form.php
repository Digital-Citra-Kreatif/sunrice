<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

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
}
