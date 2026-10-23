<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use BackedEnum;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Settings\UpdateSettings;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\Forms\Form;

/**
 * Settings fields on a page of a plugin's own (Panel::settingsPage()),
 * drawn and checked as the Settings page does: `props()` for
 * `FormSections` from `@imajiner/admin-sdk/ui`, and `validate()` turns the
 * page's input into the values SaveSettings saves. The form holds some
 * of the class's properties: those not on its own form, usually.
 *
 *     $fields = SettingsFields::make(SiteSettings::class, SiteSettings::structuredDataForm(new Form));
 *     return Inertia::render('Cms/Seo/Edit', ['structured' => $fields->props()]);
 *     // update():
 *     $save->handle(app(SiteSettings::class), $fields->validate($request->input('structured', [])));
 *
 * @api
 */
final readonly class SettingsFields
{
    /** @var list<Field> */
    private array $fields;

    /** @param class-string<Settings> $class */
    private function __construct(public string $class, public Form $form)
    {
        $this->fields = RegisteredSettings::checkedFields($class, $form);
    }

    /**
     * Fields of the settings class `$class`; each must be one of its properties.
     *
     * @param  class-string<Settings>  $class
     */
    public static function make(string $class, Form $form): self
    {
        return new self($class, $form);
    }

    /**
     * What `FormSections` takes: `sections` (the form), `values` (the
     * saved values, else each field's default) and `secrets` (whether
     * each Secret field has a value; their values are never sent).
     *
     * @return array{sections: list<array<string, mixed>>, values: array<string, mixed>, secrets: array<string, bool>}
     */
    public function props(): array
    {
        $current = (new ($this->class)())->toArray();
        $values = [];
        $secrets = [];
        foreach ($this->fields as $field) {
            $value = $current[$field->name];
            // Nothing saved yet: the field's default, when it has one (what applies until then).
            if (($value === '' || $value === null) && $field->formValue(null) !== null) {
                $value = $field->formValue(null);
            }
            if ($field->keepsValueWhenEmpty()) {
                $secrets[$field->name] = $value !== null && $value !== '';
                $value = null;
            }
            $values[$field->name] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return ['sections' => $this->form->toSections('edit'), 'values' => $values, 'secrets' => $secrets];
    }

    /**
     * The input (field name => value) checked as the Settings page checks
     * it, as the properties' values to give SaveSettings::handle(): only
     * these fields, and not an empty secret (it keeps its value).
     *
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        return UpdateSettings::values(new ($this->class)(), $this->fields, $input);
    }
}
