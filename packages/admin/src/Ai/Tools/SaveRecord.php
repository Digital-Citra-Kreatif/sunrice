<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Actions\Resources\SaveRecord as SaveRecordAction;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Resources\Forms\Password;

#[Description('Create a record (no "id") or change one: "values" holds only the fields to set; the others keep their values. Validated like the screen; use get_record first for the fields. Passwords can\'t be set.')]
final class SaveRecord extends ResourceTool
{
    protected string $name = 'save_record';

    public function handle(Request $request): Response
    {
        $resource = $this->findResource($request);
        if ($resource instanceof Response) {
            return $resource;
        }
        $user = $this->user();
        $id = $request->get('id');
        $record = $id === null ? $resource->class::newRecord() : $this->findRecord($resource, $id);
        if ($record === null) {
            return Response::error('No such record.');
        }
        $operation = $record->exists ? 'edit' : 'create';
        if ($user === null || ! $resource->allows($user, $record->exists ? 'update' : 'create', $record->exists ? $record : null)) {
            return $this->denied($record->exists ? 'change this record' : 'create records here');
        }
        $values = self::values($request->get('values'));
        if ($values === null) {
            return Response::error('Send "values": an object of field name => value.');
        }

        $fields = $resource->form()->fields($operation);
        // Passwords are never set by an agent: people choose their own (a new user can be invited).
        foreach ($fields as $field) {
            if ($field instanceof Password && (array_key_exists($field->name, $values) || array_key_exists($field->name.'_confirmation', $values))) {
                return Response::error("Passwords can't be set with AI access. Leave \"{$field->name}\" out: a person sets it on the screen, or a new user chooses their own from an invitation (when the form has \"invite\").");
            }
        }
        $input = [...self::currentValues($fields, $record->exists ? $record : null), ...$values];
        try {
            $changed = app(SaveRecordAction::class)->handle($resource, $record, $input, $operation);
        } catch (ValidationException $e) {
            return Response::error('Not saved: '.implode(' ', array_filter($e->validator->errors()->all(), 'is_string')));
        }
        $title = $resource->class::title($record);
        if ($operation === 'create') {
            app(ActivityLog::class)->record('created', $resource->key(), $record, $title);
        } else {
            app(ActivityLog::class)->updated($resource->key(), $record, $title, $changed);
        }

        return $this->json(['saved' => true, 'id' => $record->getKey(), 'title' => $title, 'changed' => $operation === 'create' ? null : $changed]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->required(),
            'id' => $schema->string()->description('The record to change; leave out to create one.'),
            'values' => $schema->object()->description('Field name => value.')->required(),
        ];
    }
}
