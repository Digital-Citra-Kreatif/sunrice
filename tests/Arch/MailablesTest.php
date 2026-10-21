<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;

/*
 * A Mailable's public properties reach its view after `Content(with: …)`,
 * overriding keys of the same name: ReviewRequested printed the whole
 * requester (a user record) instead of their name (NEW-editor-1). So a
 * key a mailable gives its view never shares a name with one of its public
 * properties, unless it is that very property.
 */
it('gives no mailable view a key its public properties would override', function () {
    $checked = 0;
    foreach (glob(dirname(__DIR__, 2).'/packages/*/src/Mail/*.php') ?: [] as $file) {
        $source = (string) file_get_contents($file);
        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
            continue;
        }
        $class = $namespace[1].'\\'.basename($file, '.php');
        if (! is_subclass_of($class, Mailable::class)) {
            continue;
        }
        $checked++;
        $public = array_map(fn (ReflectionProperty $p): string => $p->getName(), array_filter(
            (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $p): bool => $p->getDeclaringClass()->getName() === $class,
        ));
        preg_match_all("/^\s*'([A-Za-z_]+)' => (.*)$/m", $source, $keys, PREG_SET_ORDER);
        foreach ($keys as [, $key, $value]) {
            if (in_array($key, $public, true)) {
                expect(rtrim(trim($value), ','))->toBe('$this->'.$key, "{$class}: the view's \"{$key}\" is overridden by the public property \${$key}");
            }
        }
    }
    expect($checked)->toBeGreaterThan(3);
});
