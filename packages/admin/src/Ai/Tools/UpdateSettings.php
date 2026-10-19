<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Settings\UpdateSettings as UpdateSettingsAction;

#[Description('Change settings in a section: "values" holds only the settings to change; the others keep their values. Validated like the Settings page.')]
final class UpdateSettings extends ResourceTool
{
    protected string $name = 'update_settings';

    public function handle(Request $request): Response
    {
        $section = GetSettings::section($request->get('section'));
        $user = $this->user();
        if ($section === null || $user === null || ! $section->allows($user)) {
            return Response::error('No such settings section, or you may not edit it.');
        }
        $values = self::values($request->get('values'));
        if ($values === null) {
            return Response::error('Send "values": an object of setting => value.');
        }
        $current = [];
        foreach ($section->load()->toArray() as $name => $value) {
            $current[$name] = $value instanceof BackedEnum ? $value->value : $value;
        }
        foreach ($section->fields() as $field) {
            if ($field->keepsValueWhenEmpty()) {
                $current[$field->name] = null; // an empty secret keeps its value
            }
        }

        try {
            $changed = app(UpdateSettingsAction::class)->handle($section, [...$current, ...$values]);
        } catch (ValidationException $e) {
            return Response::error('Not saved: '.implode(' ', array_filter($e->validator->errors()->all(), 'is_string')));
        }

        return $this->json(['saved' => true, 'changed' => $changed]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['section' => $schema->string()->required(), 'values' => $schema->object()->required()];
    }
}
