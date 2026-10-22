<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Settings\Settings;

/**
 * Records the files a settings section's media pickers hold as used
 * (MediaUsages, type `sunrice.settings`, the section's key), so the media
 * library shows the logo as used by "Settings: Branding" and warns before
 * it's trashed. SaveSettings runs it on every save.
 *
 * @internal
 */
final class RecordSettingsMedia
{
    public function __construct(private MediaUsages $usages) {}

    public function handle(Settings $settings): void
    {
        $pickers = array_filter($settings::form(new Form)->fields('edit'), fn ($field): bool => $field instanceof MediaPicker);
        if ($pickers === []) {
            return;
        }
        $values = $settings->toArray();
        $ids = [];
        foreach ($pickers as $picker) {
            foreach ((array) ($values[$picker->name] ?? []) as $id) {
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    $ids[] = (int) $id;
                }
            }
        }
        $route = 'sunrice.admin.settings.edit';
        $this->usages->sync('sunrice.settings', $settings::key(), $ids, 'Settings: '.$settings::label(), Route::has($route) ? route($route, $settings::key()) : null);
    }
}
