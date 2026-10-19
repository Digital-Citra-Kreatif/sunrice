<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Contracts\Auth\Access\Authorizable;
use ReflectionProperty;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;

/**
 * A settings section on the panel, with the permission to edit it.
 *
 * @internal
 */
final readonly class RegisteredSettings
{
    /** @param class-string<Settings> $class */
    public function __construct(public string $class, public string $permission) {}

    public function key(): string
    {
        return $this->class::key();
    }

    /** The section with its stored values. */
    public function load(): Settings
    {
        return new ($this->class)();
    }

    /**
     * The section's form fields, checked against its properties.
     *
     * @return list<Field>
     */
    public function fields(?Form $form = null): array
    {
        $form ??= $this->class::form(new Form);
        $properties = array_map(fn (ReflectionProperty $property) => $property->getName(), SettingsStore::properties($this->class));
        $fields = $form->fields('edit');
        foreach ($fields as $field) {
            if (! in_array($field->name, $properties, true)) {
                throw new PanelMisconfigured("The settings form {$this->class} has the field \"{$field->name}\", which isn't one of its properties.");
            }
            if ($field instanceof Password || $field->isSavedAfterRecord()) {
                throw new PanelMisconfigured("The settings form {$this->class} can't use the field \"{$field->name}\": settings are typed properties (use Secret for secrets).");
            }
        }

        return $fields;
    }

    public function allows(Authorizable $user): bool
    {
        return $user->can($this->permission);
    }
}
