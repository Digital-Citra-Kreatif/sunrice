<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Settings;

use BackedEnum;
use Illuminate\Support\Str;
use ReflectionProperty;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\Encrypted;
use Sunrice\Admin\Settings\Settings;

/**
 * Saves new values for a settings section and announces what changed.
 * The files its media pickers hold are recorded as used on every save,
 * changed or not (RecordSettingsMedia), so the media library doesn't offer
 * to delete the logo.
 *
 * @api
 */
final class SaveSettings
{
    public function __construct(private ActivityLog $activity, private RecordSettingsMedia $media) {}

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
            $settings->save(); // fires SettingsSaved
            $this->activity->record('updated', 'settings', $settings::key(), $settings::label().' settings', ['changed' => $changed, 'values' => self::values($settings, $changed, $before, $after)]);
        }
        // Every save, changed or not: a section saved before usages were recorded gets them now.
        $this->media->handle($settings);

        return $changed;
    }

    /**
     * What the changed settings were and became, for the activity log:
     * short text, never a secret (encrypted, or a Secret field).
     *
     * @param  list<string>  $changed
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: string, to: string}>
     */
    private static function values(Settings $settings, array $changed, array $before, array $after): array
    {
        $secrets = [];
        foreach ($settings::form(new Form)->fields('edit') as $field) {
            if ($field->keepsValueWhenEmpty()) {
                $secrets[] = $field->name;
            }
        }
        $values = [];
        foreach ($changed as $name) {
            if (! in_array($name, $secrets, true) && (new ReflectionProperty($settings, $name))->getAttributes(Encrypted::class) === []) {
                $values[$name] = ['from' => self::text($before[$name]), 'to' => self::text($after[$name])];
            }
        }

        return $values;
    }

    private static function text(mixed $value): string
    {
        $text = match (true) {
            $value === null, $value === '', $value === [] => '(empty)',
            is_bool($value) => $value ? 'on' : 'off',
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };

        return Str::limit(str_replace(["\r\n", "\n"], ' ', $text), 80);
    }
}
