<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Settings\Settings;

/**
 * Saves new values for a settings section and announces what changed.
 * The files its media pickers hold are recorded as used (MediaUsages,
 * type `sunrice.settings`), so the media library doesn't offer to delete
 * the logo.
 *
 * @api
 */
final class SaveSettings
{
    public function __construct(private ActivityLog $activity, private MediaUsages $usages) {}

    /**
     * @param  array<string, mixed>  $values  property => new value; others stay
     * @return list<string> the settings that changed
     */
    public function handle(Settings $settings, array $values): array
    {
        $before = $settings->toArray();
        $after = $settings->fill($values)->toArray();
        $changed = array_values(array_filter(array_keys($after), fn (string $name) => $after[$name] !== $before[$name]));

        if ($changed !== []) {
            $settings->save();
            SettingsSaved::dispatch($settings::key(), $changed);
            $this->activity->record('updated', 'settings', $settings::key(), $settings::label().' settings', ['changed' => $changed]);
            $this->recordMedia($settings);
        }

        return $changed;
    }

    /** What the section's media pickers hold now, as the files it uses. */
    private function recordMedia(Settings $settings): void
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
