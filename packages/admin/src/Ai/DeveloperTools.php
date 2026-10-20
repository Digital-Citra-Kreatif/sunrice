<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Illuminate\Support\Str;

/**
 * The AI tools for working on the app itself (config
 * `sunrice-admin.ai.developer_tools`): Blade views, allowed console
 * commands and the error log. Off unless turned on.
 *
 * @internal
 */
final class DeveloperTools
{
    /** Their permissions: only given by name, never by "*" in a default role. */
    public const PERMISSIONS = ['sunrice.views.view', 'sunrice.views.update', 'sunrice.commands.run', 'sunrice.logs.view'];

    /** How many earlier versions of a view are kept. */
    public const VERSIONS = 20;

    public static function enabled(): bool
    {
        $enabled = config('sunrice-admin.ai.developer_tools.enabled', false);

        return config('sunrice-admin.ai.enabled', true) !== false && ($enabled === true || $enabled === 'true' || $enabled === '1' || $enabled === 1);
    }

    public static function viewsPath(): string
    {
        $path = config('sunrice-admin.ai.developer_tools.views_path');

        return rtrim(is_string($path) && $path !== '' ? $path : resource_path('views'), '/');
    }

    public static function logsPath(): string
    {
        $path = config('sunrice-admin.ai.developer_tools.logs_path');

        return rtrim(is_string($path) && $path !== '' ? $path : storage_path('logs'), '/');
    }

    /** Where earlier versions of views are kept. */
    public static function versionsPath(): string
    {
        return storage_path('app/sunrice/view-versions');
    }

    /** @return list<string> */
    public static function commands(): array
    {
        $commands = config('sunrice-admin.ai.developer_tools.commands', []);

        return is_array($commands) ? array_values(array_filter($commands, is_string(...))) : [];
    }

    public static function allowsCommand(string $name): bool
    {
        return $name !== '' && Str::is(self::commands(), $name);
    }

    /**
     * A view's name as Laravel writes it ("cms.article"), from that, a path
     * ("cms/article") or a file name ("cms/article.blade.php"); null when
     * it isn't one: only lowercase letters, numbers, "-" and "_" between
     * the dots, so it can't leave the views folder.
     */
    public static function viewName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }
        $name = str_replace('/', '.', trim(preg_replace('/\.blade\.php$/', '', trim($name)) ?? ''));

        return preg_match('/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)*$/', $name) === 1 && strlen($name) <= 200 ? $name : null;
    }

    public static function viewFile(string $name): string
    {
        return self::viewsPath().'/'.str_replace('.', '/', $name).'.blade.php';
    }
}
