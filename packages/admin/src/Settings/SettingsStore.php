<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use BackedEnum;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Sunrice\Admin\Exceptions\PanelMisconfigured;

/**
 * Reads and writes settings sections: all rows in one query, cached until
 * a section is saved; values converted to the properties' types; secrets
 * encrypted.
 *
 * @internal
 */
final class SettingsStore
{
    public const TABLE = 'sunrice_settings';

    public const CACHE_KEY = 'sunrice-admin.settings';

    private const SCALARS = ['string', 'int', 'float', 'bool', 'array'];

    /** @var array<string, array<string, mixed>>|null stored values by section key, for this request */
    private ?array $rows = null;

    /** @var array<class-string<Settings>, list<ReflectionProperty>> */
    private static array $properties = [];

    public function load(Settings $settings): void
    {
        $stored = $this->rows()[$settings::key()] ?? [];
        foreach (self::properties($settings::class) as $property) {
            if (! array_key_exists($property->getName(), $stored)) {
                continue;
            }
            $value = $stored[$property->getName()];
            if (self::isEncrypted($property)) {
                try {
                    $value = is_string($value) ? json_decode(Crypt::decryptString($value), true) : null;
                } catch (DecryptException) {
                    continue; // encrypted with another app key: the default applies
                }
            }
            $property->setValue($settings, self::convert($property, $value, $property->getDefaultValue()));
        }
    }

    /** @param array<string, mixed> $values */
    public function fill(Settings $settings, array $values): void
    {
        foreach (self::properties($settings::class) as $property) {
            if (array_key_exists($property->getName(), $values)) {
                $property->setValue($settings, self::convert($property, $values[$property->getName()], $property->getValue($settings)));
            }
        }
    }

    /** @return array<string, mixed> */
    public function values(Settings $settings): array
    {
        $values = [];
        foreach (self::properties($settings::class) as $property) {
            $values[$property->getName()] = $property->getValue($settings);
        }

        return $values;
    }

    public function save(Settings $settings): void
    {
        $stored = [];
        foreach (self::properties($settings::class) as $property) {
            $value = $property->getValue($settings);
            $value = $value instanceof BackedEnum ? $value->value : $value;
            $stored[$property->getName()] = self::isEncrypted($property) ? Crypt::encryptString((string) json_encode($value)) : $value;
        }

        $now = now();
        DB::table(self::TABLE)->upsert(
            [['key' => $settings::key(), 'values' => json_encode($stored, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]],
            ['key'],
            ['values', 'updated_at'],
        );
        $this->forget();
    }

    /** Drop the cached values (after saving, or when the database changed underneath). */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->rows = null;
    }

    /**
     * The section's settings: its public, typed properties, checked once.
     *
     * @param  class-string<Settings>  $class
     * @return list<ReflectionProperty>
     */
    public static function properties(string $class): array
    {
        if (isset(self::$properties[$class])) {
            return self::$properties[$class];
        }

        $properties = [];
        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $type = $property->getType();
            $name = "{$class}::\${$property->getName()}";
            if (! $type instanceof ReflectionNamedType || ! (in_array($type->getName(), self::SCALARS, true) || is_subclass_of($type->getName(), BackedEnum::class))) {
                throw new PanelMisconfigured("The setting {$name} needs a type: string, int, float, bool, array or a backed enum (optionally nullable).");
            }
            if (! $property->hasDefaultValue() || $property->isReadOnly()) {
                throw new PanelMisconfigured("The setting {$name} needs a default value (and can't be readonly).");
            }
            $properties[] = $property;
        }

        return self::$properties[$class] = $properties;
    }

    /**
     * A value converted to the property's type, or `$fallback` when it
     * doesn't fit (stored data from an older version, say).
     */
    private static function convert(ReflectionProperty $property, mixed $value, mixed $fallback): mixed
    {
        $type = $property->getType();
        if (! $type instanceof ReflectionNamedType) {
            return $fallback;
        }
        $name = $type->getName();
        if ($value === null || ($value === '' && $name !== 'string')) {
            return $type->allowsNull() ? null : ($name === 'string' ? '' : $fallback);
        }

        return match (true) {
            $name === 'string' => is_scalar($value) ? (string) $value : $fallback,
            $name === 'int' => is_int($value) ? $value : (filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $fallback),
            $name === 'float' => is_int($value) || is_float($value) ? (float) $value : (is_numeric($value) ? (float) $value : $fallback),
            $name === 'bool' => is_bool($value) ? $value : (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback),
            $name === 'array' => is_array($value) ? $value : $fallback,
            is_subclass_of($name, BackedEnum::class) => $value instanceof $name ? $value : ((is_int($value) || is_string($value)) ? ($name::tryFrom($value) ?? $fallback) : $fallback),
            default => $fallback,
        };
    }

    private static function isEncrypted(ReflectionProperty $property): bool
    {
        return $property->getAttributes(Encrypted::class) !== [];
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            /** @var array<string, array<string, mixed>> $cached */
            return $this->rows = $cached;
        }

        try {
            $rows = DB::table(self::TABLE)->pluck('values', 'key')->all();
        } catch (QueryException) {
            return []; // not migrated yet: defaults, and nothing cached
        }
        $decoded = [];
        foreach ($rows as $key => $json) {
            $values = is_string($json) ? json_decode($json, true) : null;
            /** @var array<string, mixed> $section */
            $section = is_array($values) ? $values : [];
            $decoded[(string) $key] = $section;
        }
        Cache::forever(self::CACHE_KEY, $decoded);

        return $this->rows = $decoded;
    }
}
