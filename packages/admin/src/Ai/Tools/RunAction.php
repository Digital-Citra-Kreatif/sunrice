<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;

#[Description('Run one of a resource\'s actions ("publish", "deactivate" …, as get_record or list_records show) on records by id. Row actions run on each record; bulk actions on all of them at once.')]
final class RunAction extends ResourceTool
{
    protected string $name = 'run_action';

    public function handle(Request $request): Response
    {
        $resource = $this->findResource($request);
        if ($resource instanceof Response) {
            return $resource;
        }
        $key = $request->get('action');
        $ids = $request->get('ids');
        if (! is_string($key) || ! is_array($ids) || $ids === [] || count($ids) > 100) {
            return Response::error('Send "action" and "ids" (1 to 100 record ids).');
        }
        $actions = array_values(array_filter($resource->table()->getActions(), fn (Action $a) => $a->key === $key));
        $action = $actions[0] ?? null;
        if ($action === null || $action->isUrl()) {
            return Response::error("This resource has no action \"{$key}\" to run.");
        }
        $records = $resource->class::query()->whereKey(array_filter($ids, fn (mixed $id) => is_int($id) || is_string($id)))->get();
        $user = $this->user();
        foreach ($records as $record) {
            if ($user === null || ! $resource->allows($user, $action->getAbility(), $record) || ! $action->allows($user, $record) || (! $action->isBulk && ! $action->isVisibleFor($record))) {
                return $this->denied("run \"{$action->key}\" on ".$resource->class::title($record));
            }
        }
        if ($records->isEmpty()) {
            return Response::error('No such records.');
        }

        try {
            $messages = $action->isBulk ? [$action->run($records)] : $records->map(fn (Model $record) => $action->run($record))->all();
        } catch (ActionFailed $failed) {
            return Response::error($failed->getMessage());
        }
        $records->each(fn (Model $record) => app(ActivityLog::class)->record($action->key, $resource->key(), $record, $resource->class::title($record)));

        return $this->json(['done' => $records->count(), 'messages' => array_values(array_filter($messages))]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->required(),
            'action' => $schema->string()->required(),
            'ids' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
