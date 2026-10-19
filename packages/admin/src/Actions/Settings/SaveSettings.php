<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Settings\Settings;

/**
 * Saves new values for a settings section and announces what changed.
 *
 * @api
 */
final class SaveSettings
{
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
        }

        return $changed;
    }
}
