<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Views;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ParseError;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\DeveloperTools;
use Sunrice\Admin\Ai\ViewVersions;
use Throwable;

/**
 * Create or change a Blade view under the views folder
 * (DeveloperTools::viewsPath()), as an AI agent's save_view does. The
 * view must compile to valid PHP; the earlier content is kept as a
 * version (ViewVersions), and the change goes in the activity log.
 *
 * @internal
 */
final class SaveView
{
    /** Views bigger than this are refused (bytes). */
    public const MAX_SIZE = 512 * 1024;

    public function __construct(private ViewVersions $versions, private ActivityLog $activity) {}

    /**
     * @return array{created: bool, version: string|null} the version the old content was kept as
     *
     * @throws ValidationException
     */
    public function handle(string $name, string $content): array
    {
        if (strlen($content) > self::MAX_SIZE) {
            throw ValidationException::withMessages(['content' => 'The view is bigger than 512 KB.']);
        }
        try {
            token_get_all(Blade::compileString($content), TOKEN_PARSE);
        } catch (ParseError $e) {
            throw ValidationException::withMessages(['content' => "The view doesn't compile: {$e->getMessage()} (line {$e->getLine()} of the compiled PHP)."]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['content' => "The view doesn't compile: {$e->getMessage()}"]);
        }

        $file = DeveloperTools::viewFile($name);
        $exists = is_file($file);
        $version = null;
        if ($exists) {
            $old = File::get($file);
            if ($old === $content) {
                return ['created' => false, 'version' => null];
            }
            $version = $this->versions->keep($name, $old);
        }
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $content);
        self::forgetCompiled($file);
        $this->activity->record($exists ? 'updated' : 'created', 'view', $name, $name);

        return ['created' => ! $exists, 'version' => $version];
    }

    /** Drop the compiled copy so the next request uses the new view. */
    public static function forgetCompiled(string $file): void
    {
        $compiled = Blade::getCompiledPath($file);
        if (is_file($compiled)) {
            File::delete($compiled);
        }
    }
}
