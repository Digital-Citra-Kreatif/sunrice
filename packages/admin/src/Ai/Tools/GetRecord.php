<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Resources\Tables\Action;

#[IsReadOnly]
#[Description('Read a record\'s form: its fields (name, type, label, required, options) and current values, and the actions you may run on it. Without "id": the fields to create one. Passwords and secrets are never shown.')]
final class GetRecord extends ResourceTool
{
    protected string $name = 'get_record';

    public function handle(Request $request): Response
    {
        $resource = $this->findResource($request);
        if ($resource instanceof Response) {
            return $resource;
        }
        $user = $this->user();
        $id = $request->get('id');
        $record = $id === null ? null : $this->findRecord($resource, $id);
        if ($id !== null && $record === null) {
            return Response::error('No such record.');
        }
        if ($user === null || ! $resource->allows($user, $record === null ? 'create' : 'view', $record)) {
            return $this->denied($record === null ? 'create records here' : 'view this record');
        }
        $fields = $resource->form()->fields($record === null ? 'create' : 'edit');

        return $this->json([
            'resource' => $resource->key(),
            'id' => $record?->getKey(),
            'title' => $record === null ? null : $resource->class::title($record),
            'fields' => self::describeFields($fields, $record),
            'values' => self::currentValues($fields, $record),
            'can' => $record === null ? [] : [
                'update' => $resource->allows($user, 'update', $record),
                'delete' => $resource->allows($user, 'delete', $record),
            ],
            'actions' => $record === null ? [] : array_values(array_map(
                fn (Action $action) => $action->toArray(),
                array_filter($resource->table()->getActions(), fn (Action $action) => ! $action->isBulk && $action->isVisibleFor($record) && $resource->allows($user, $action->getAbility(), $record)),
            )),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->required(),
            'id' => $schema->string()->description('The record\'s id; leave out for the create form.'),
        ];
    }
}
