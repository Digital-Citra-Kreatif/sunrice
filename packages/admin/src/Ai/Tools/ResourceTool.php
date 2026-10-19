<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Resources\RegisteredResource;

/**
 * Helpers for the tools that work with resources: find the resource and
 * the record the agent names, checking the same abilities as the screens.
 *
 * @internal
 */
abstract class ResourceTool extends Tool
{
    protected function user(): ?Authorizable
    {
        $user = auth(app(Panel::class)->getGuard())->user();

        return $user instanceof Authorizable ? $user : null;
    }

    /** The resource named by the "resource" argument, if the user may view it. */
    protected function findResource(Request $request): RegisteredResource|Response
    {
        $key = $request->get('resource');
        $resource = is_string($key) ? (app(Panel::class)->getResources()[$key] ?? null) : null;
        $user = $this->user();
        if ($resource === null || $user === null || ! $resource->allows($user, 'view')) {
            return Response::error('No such resource. get_panel_info lists the resources you can use.');
        }

        return $resource;
    }

    protected function findRecord(RegisteredResource $resource, mixed $id): ?Model
    {
        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        return $resource->class::query()->whereKey($id)->first();
    }

    /**
     * A form's fields for an agent: name, type, label, required, options.
     *
     * @param  list<Field>  $fields
     * @return list<array<string, mixed>>
     */
    protected static function describeFields(array $fields, ?Model $record): array
    {
        return array_map(function (Field $field) use ($record): array {
            $info = $field->toArray($record);
            unset($info['kind'], $info['span'], $info['placeholder'], $info['autocomplete']);

            return array_filter($info, fn (mixed $value) => $value !== null && $value !== false && $value !== []);
        }, $fields);
    }

    /**
     * An agent's "values" argument: field name => value.
     *
     * @return array<string, mixed>|null
     */
    protected static function values(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }
        $result = [];
        foreach ($values as $name => $value) {
            if (is_string($name)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Current values of the form's fields (secrets and passwords: null),
     * so an agent's partial update keeps the rest.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    protected static function currentValues(array $fields, ?Model $record): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field->name] = $field->formValue($record);
        }

        return $values;
    }
}
