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
 * A settings section on the panel, with the permission to edit it. For
 * AI agents, a settings page's fields (Panel::settingsPage(fields:)) are
 * one too: its own key, label and form, some of the class's properties.
 *
 * @internal
 */
final readonly class RegisteredSettings
{
    /** @param class-string<Settings> $class */
    public function __construct(
        public string $class,
        public string $permission,
        private ?Form $form = null,
        private ?string $key = null,
        private ?string $label = null,
    ) {}

    public function key(): string
    {
        return $this->key ?? $this->class::key();
    }

    public function label(): string
    {
        return $this->label ?? $this->class::label();
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
        return self::checkedFields($this->class, $form ?? $this->form ?? $this->class::form(new Form));
    }

    /**
     * A form's fields for a settings class, checked against its properties
     * (also for SettingsFields, a part of its fields on another page).
     *
     * @param  class-string<Settings>  $class
     * @return list<Field>
     */
    public static function checkedFields(string $class, Form $form): array
    {
        $properties = array_map(fn (ReflectionProperty $property) => $property->getName(), SettingsStore::properties($class));
        $fields = $form->fields('edit');
        foreach ($fields as $field) {
            if (! in_array($field->name, $properties, true)) {
                throw new PanelMisconfigured("The settings form {$class} has the field \"{$field->name}\", which isn't one of its properties.");
            }
            if ($field instanceof Password || $field->isSavedAfterRecord()) {
                throw new PanelMisconfigured("The settings form {$class} can't use the field \"{$field->name}\": settings are typed properties (use Secret for secrets).");
            }
        }

        return $fields;
    }

    /** Whether it's in use (Settings::available()). */
    public function available(): bool
    {
        return $this->class::available();
    }

    /** Whether `$user` may edit it: it's in use and they have its permission. */
    public function allows(Authorizable $user): bool
    {
        return $this->available() && $user->can($this->permission);
    }
}
