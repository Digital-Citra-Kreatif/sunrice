<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Views;

use Illuminate\Support\Facades\File;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\DeveloperTools;
use Sunrice\Admin\Ai\ViewVersions;

/**
 * Delete a Blade view under the views folder, keeping its content as a
 * version (ViewVersions) so it can be brought back.
 *
 * @internal
 */
final class DeleteView
{
    public function __construct(private ViewVersions $versions, private ActivityLog $activity) {}

    /** @return string|null the version its content was kept as; null when there was no such view */
    public function handle(string $name): ?string
    {
        $file = DeveloperTools::viewFile($name);
        if (! is_file($file)) {
            return null;
        }
        $version = $this->versions->keep($name, File::get($file));
        File::delete($file);
        SaveView::forgetCompiled($file);
        $this->activity->record('deleted', 'view', $name, $name);

        return $version;
    }
}
