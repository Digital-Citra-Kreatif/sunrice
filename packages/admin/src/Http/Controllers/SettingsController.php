<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use BackedEnum;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\RegisteredSettings;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Admin\Settings\SettingsStore;

/**
 * The Settings page: each section's form, for users with its permission.
 *
 * @internal
 */
final class SettingsController
{
    public function __construct(private Panel $panel) {}

    /** The first section the user may edit. */
    public function index(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        foreach ($this->panel->getSettings() as $section) {
            if ($section->allows($user)) {
                return redirect()->route('sunrice.admin.settings.edit', $section->key());
            }
        }
        abort(403);
    }

    public function edit(Request $request, string $section): Response
    {
        $user = $this->user($request);
        $registered = $this->section($user, $section);
        $settings = $registered->load();
        $form = $registered->class::form(new Form);

        $current = $settings->toArray();
        $values = [];
        $secrets = [];
        foreach ($this->fields($registered, $form) as $field) {
            $value = $current[$field->name];
            if ($field->keepsValueWhenEmpty()) {
                $secrets[$field->name] = $value !== null && $value !== '';
                $value = null;
            }
            $values[$field->name] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return Inertia::render('Settings/Edit', [
            'section' => ['key' => $registered->key(), 'label' => $registered->class::label(), 'description' => $registered->class::description()],
            'sections' => array_values(array_map(
                fn (RegisteredSettings $other) => ['key' => $other->key(), 'label' => $other->class::label()],
                array_filter($this->panel->getSettings(), fn (RegisteredSettings $other) => $other->allows($user)),
            )),
            'form' => $form->toSections('edit'),
            'values' => $values,
            'secrets' => $secrets,
        ]);
    }

    public function update(Request $request, string $section, SaveSettings $save): RedirectResponse
    {
        $registered = $this->section($this->user($request), $section);
        $settings = $registered->load();
        $current = $settings->toArray();
        $fields = array_values(array_filter($this->fields($registered, $registered->class::form(new Form)), fn (Field $field) => ! $field->isDisabled(null)));

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
        $validated = $request->validate($rules, [], $attributes);

        // Only the form's fields are written; empty secrets keep their value.
        $values = [];
        foreach ($fields as $field) {
            $value = $validated[$field->name] ?? null;
            if ((! array_key_exists($field->name, $validated) && ! $field instanceof Toggle) || ($field->keepsValueWhenEmpty() && ($value === null || $value === ''))) {
                continue;
            }
            $values[$field->name] = $field instanceof Toggle ? (bool) $value : $value;
        }
        $save->handle($settings, $values);

        return back()->with('success', $registered->class::label().' settings saved.');
    }

    /**
     * The form's fields, checked against the section's properties.
     *
     * @return list<Field>
     */
    private function fields(RegisteredSettings $section, Form $form): array
    {
        $properties = array_map(fn (\ReflectionProperty $property) => $property->getName(), SettingsStore::properties($section->class));
        $fields = $form->fields('edit');
        foreach ($fields as $field) {
            if (! in_array($field->name, $properties, true)) {
                throw new PanelMisconfigured("The settings form {$section->class} has the field \"{$field->name}\", which isn't one of its properties.");
            }
            if ($field instanceof Password || $field->isSavedAfterRecord()) {
                throw new PanelMisconfigured("The settings form {$section->class} can't use the field \"{$field->name}\": settings are typed properties (use Secret for secrets).");
            }
        }

        return $fields;
    }

    private function section(Authorizable $user, string $key): RegisteredSettings
    {
        $section = $this->panel->getSettings()[$key] ?? null;
        abort_if($section === null, 404);
        abort_unless($section->allows($user), 403);

        return $section;
    }

    private function user(Request $request): Authorizable
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 403);

        return $user;
    }
}
