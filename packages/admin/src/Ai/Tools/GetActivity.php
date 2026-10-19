<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Panel;

#[IsReadOnly]
#[Description('Read the activity log, newest first: who changed what and when, security events (source "security"), and what AI agents did (source "ai"). "mine": only your own lines. Answers "what changed recently?" or "what did you do?".')]
final class GetActivity extends Tool
{
    protected string $name = 'get_activity';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.activity.view')) {
            return $this->denied('view the activity log');
        }
        $source = $request->get('source');
        $type = $request->get('type');
        $subject = $request->get('subject');
        $limit = $request->get('limit');
        $user = auth(app(Panel::class)->getGuard())->user();
        $mine = $request->get('mine') === true ? $user?->getAuthIdentifier() : null;
        $mine = is_scalar($mine) ? (string) $mine : null;
        $subject = is_string($type) && (is_string($subject) || is_int($subject)) ? (string) $subject : null;

        $lines = Activity::query()
            ->when(is_string($source) && in_array($source, ActivityLog::SOURCES, true), fn (Builder $q) => $q->where('source', $source))
            ->when(is_string($type), fn (Builder $q) => $q->where('subject_type', $type))
            ->when($subject !== null, fn (Builder $q) => $q->where('subject_id', $subject))
            ->when($mine !== null, fn (Builder $q) => $q->where('user_id', $mine))
            ->orderByDesc('id')
            ->limit(is_int($limit) ? max(1, min(100, $limit)) : 30)
            ->get();

        return $this->json(['activity' => $lines->map(fn (Activity $a) => array_filter([
            'at' => $a->created_at?->toIso8601String(),
            'source' => $a->source,
            'via' => $a->via,
            'user' => $a->user_name,
            'action' => $a->action,
            'type' => $a->subject_type,
            'id' => $a->subject_id,
            'name' => $a->subject_label,
            'changed' => $a->properties['changed'] ?? null,
            'note' => $a->properties['note'] ?? null,
            'ip' => $a->ip_address,
        ], fn (mixed $v) => $v !== null))->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->enum(ActivityLog::SOURCES),
            'type' => $schema->string()->description('A kind of thing: a resource key ("products"), "settings", "media", "account" …'),
            'subject' => $schema->string()->description('With "type": one item\'s history, by id.'),
            'mine' => $schema->boolean(),
            'limit' => $schema->integer()->description('Up to 100; 30 by default.'),
        ];
    }
}
