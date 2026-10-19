<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\ErrorLog;

#[IsReadOnly]
#[Description('Read the app\'s error log (storage/logs), newest first: each entry\'s time, level, message and the start of its stack trace. By default the newest file, errors and worse; "level" lowers or raises that, "search" keeps entries containing a text. Secrets that look like passwords or tokens are hidden.')]
final class ReadLog extends DeveloperTool
{
    protected string $name = 'read_log';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.logs.view')) {
            return $this->denied('read the error log');
        }
        $log = app(ErrorLog::class);
        $files = $log->files();
        $file = $request->get('file');
        $file = is_string($file) && $file !== '' ? $file : ($files[0]['name'] ?? null);
        if ($file === null) {
            return $this->json(['files' => [], 'entries' => []]);
        }
        $level = $request->get('level');
        $search = $request->get('search');
        $limit = $request->get('limit');
        $entries = $log->entries($file, is_string($level) ? $level : 'error', is_string($search) ? $search : null, is_int($limit) ? max(1, min(100, $limit)) : 20);

        return $entries === null
            ? Response::error("No log file \"{$file}\". Files: ".implode(', ', array_column($files, 'name')).'.')
            : $this->json(['file' => $file, 'files' => array_slice($files, 0, 30), 'entries' => $entries]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('A file from "files", like "laravel.log"; the newest by default.'),
            'level' => $schema->string()->enum(ErrorLog::LEVELS)->description('The lowest level shown; "error" by default.'),
            'search' => $schema->string(),
            'limit' => $schema->integer()->description('Up to 100; 20 by default.'),
        ];
    }
}
