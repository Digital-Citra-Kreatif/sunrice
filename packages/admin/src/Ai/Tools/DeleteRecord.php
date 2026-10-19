<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Sunrice\Admin\Activity\ActivityLog;

#[IsDestructive]
#[Description('Delete a record, as its screen\'s delete button does. Ask the user first.')]
final class DeleteRecord extends ResourceTool
{
    protected string $name = 'delete_record';

    public function handle(Request $request): Response
    {
        $resource = $this->findResource($request);
        if ($resource instanceof Response) {
            return $resource;
        }
        $record = $this->findRecord($resource, $request->get('id'));
        if ($record === null) {
            return Response::error('No such record.');
        }
        $user = $this->user();
        if ($user === null || ! $resource->allows($user, 'delete', $record)) {
            return $this->denied('delete this record');
        }
        $title = $resource->class::title($record);
        $record->delete();
        app(ActivityLog::class)->record('deleted', $resource->key(), $record, $title);

        return $this->json(['deleted' => true, 'title' => $title]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['resource' => $schema->string()->required(), 'id' => $schema->string()->required()];
    }
}
