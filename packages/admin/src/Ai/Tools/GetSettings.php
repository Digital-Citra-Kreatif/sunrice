<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\RegisteredSettings;

#[IsReadOnly]
#[Description('Read a settings section (get_panel_info lists them): its fields and values. Secrets show only whether they are set.')]
final class GetSettings extends ResourceTool
{
    protected string $name = 'get_settings';

    public function handle(Request $request): Response
    {
        $section = self::section($request->get('section'));
        $user = $this->user();
        if ($section === null || $user === null || ! $section->allows($user)) {
            return Response::error('No such settings section, or you may not edit it.');
        }
        $current = $section->load()->toArray();
        $values = [];
        foreach ($section->fields() as $field) {
            $value = $current[$field->name];
            $values[$field->name] = $field->keepsValueWhenEmpty()
                ? ($value === null || $value === '' ? '(not set)' : '(set)')
                : ($value instanceof BackedEnum ? $value->value : $value);
        }

        return $this->json([
            'section' => $section->key(),
            'label' => $section->class::label(),
            'fields' => self::describeFields($section->fields(), null),
            'values' => $values,
        ]);
    }

    public static function section(mixed $key): ?RegisteredSettings
    {
        return is_string($key) ? (app(Panel::class)->getSettings()[$key] ?? null) : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return ['section' => $schema->string()->required()];
    }
}
