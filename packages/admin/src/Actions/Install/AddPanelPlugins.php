<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Install;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use ParseError;
use PhpToken;
use Sunrice\Admin\Contracts\Plugin;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;

/**
 * Adds plugins installed after the panel was set up to the app's panel
 * provider: the ones installed packages offer (CreatePanelProvider::plugins(),
 * from `extra.laravel.sunrice-plugins`) that the running panel doesn't have.
 * Used by `sunrice:install`.
 *
 *     $add = app(AddPanelPlugins::class);
 *     $missing = $add->missing($panel);
 *     $file = $add->file();          // null: not one it can edit
 *     $add->handle($file, $missing); // false: the file's shape isn't recognised
 *
 * It edits `return $panel->…;` in the provider's panel() method: the
 * plugins' imports join the file's (in order), and `->plugin(X::make())`
 * ends the chain, indented like its other calls. A file it doesn't
 * recognise is left alone (instructions() says what to add by hand), the
 * result must parse before it's written, and plugins already in the chain
 * aren't added twice.
 *
 * @internal
 */
final class AddPanelPlugins
{
    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public function __construct(private CreatePanelProvider $create, private Application $app) {}

    /**
     * Plugins installed packages offer that the panel doesn't have.
     *
     * @return list<class-string<Plugin>>
     */
    public function missing(Panel $panel): array
    {
        $registered = $panel->getPlugins();

        return array_values(array_filter(
            $this->create->plugins(),
            function (string $class) use ($registered): bool {
                foreach ($registered as $plugin) {
                    if ($plugin instanceof $class) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * The file of the app's panel provider, when there is exactly one and
     * it's the app's own, writable file (not a package's).
     */
    public function file(): ?string
    {
        $providers = $this->app->getProviders(PanelProvider::class);
        if (count($providers) !== 1) {
            return null;
        }
        $provider = reset($providers);
        if (! $provider instanceof PanelProvider) {
            return null;
        }
        $class = $provider::class;
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($class);
            if (is_string($file)) {
                $file = realpath($file) ?: $file;

                return str_contains(str_replace('\\', '/', $file), '/vendor/') || ! is_writable($file) ? null : $file;
            }
        }

        return null;
    }

    /**
     * Adds the plugins to the provider's file. False (and the file left as
     * it is) when the file isn't one it recognises.
     *
     * @param  list<class-string<Plugin>>  $plugins
     */
    public function handle(string $file, array $plugins): bool
    {
        $code = is_file($file) ? file_get_contents($file) : false;
        if ($code === false) {
            return false;
        }
        $edited = $this->edit($code, $plugins);
        if ($edited === null) {
            return false;
        }
        if ($edited === $code) {
            return true;
        }
        if (file_put_contents($file, $edited, LOCK_EX) === false || file_get_contents($file) !== $edited) {
            file_put_contents($file, $code, LOCK_EX);

            return false;
        }

        return true;
    }

    /**
     * The code with the plugins added (unchanged when they're all there),
     * or null when it has no `return $panel->…;` chain in panel() to add
     * them to, or the result wouldn't parse.
     *
     * @param  list<string>  $plugins
     */
    public function edit(string $code, array $plugins): ?string
    {
        $tokens = $this->tokens($code);
        $chain = $tokens === null ? null : $this->chain($tokens);
        if ($tokens === null || $chain === null) {
            return null;
        }
        $namespace = preg_match('/^namespace\s+([\w\\\\]+)\s*;[ \t]*\n/m', $code, $match) === 1 ? $match[1] : null;
        $classAt = $this->classOffset($tokens) ?? strlen($code);
        $imports = $this->imports(substr($code, 0, $classAt));
        $grouped = preg_match('/^use\s[^;]*\{/m', substr($code, 0, $classAt)) === 1;
        $own = $this->className($tokens);

        $calls = [];
        $newImports = [];
        foreach (array_unique(array_map(fn (string $class) => ltrim($class, '\\'), $plugins)) as $class) {
            if (in_array(strtolower($class), $chain['plugins'], true)) {
                continue;
            }
            $alias = $this->aliasOf(substr($code, 0, $classAt), $class);
            $basename = class_basename($class);
            if ($alias !== null) {
                $reference = $alias;
            } elseif ($namespace === null || $grouped || isset($imports[strtolower($basename)]) || strtolower($basename) === strtolower($own ?? '')) {
                $reference = '\\'.$class;
            } else {
                $reference = $basename;
                $newImports[] = $class;
            }
            $calls[] = "->plugin({$reference}::make())";
        }
        if ($calls === []) {
            return $code;
        }

        // The calls first: they come after the imports, whose offsets don't move.
        $separator = $chain['indent'] === null ? '' : "\n".$chain['indent'];
        $code = substr($code, 0, $chain['end']).$separator.implode($separator, $calls).substr($code, $chain['end']);

        foreach ($newImports as $class) {
            $code = $this->addImport($code, $class);
        }

        return $this->tokens($code) === null ? null : $code;
    }

    /**
     * What to add by hand: the imports, then the calls for the end of
     * `return $panel->…;` in panel().
     *
     * @param  list<string>  $plugins
     * @return array{imports: list<string>, calls: list<string>}
     */
    public function instructions(array $plugins): array
    {
        return [
            'imports' => array_map(fn (string $class) => 'use '.ltrim($class, '\\').';', $plugins),
            'calls' => array_map(fn (string $class) => '->plugin('.class_basename($class).'::make())', $plugins),
        ];
    }

    /** @return list<PhpToken>|null null when the code doesn't parse */
    private function tokens(string $code): ?array
    {
        try {
            return array_values(PhpToken::tokenize($code, TOKEN_PARSE));
        } catch (ParseError) {
            return null;
        }
    }

    /**
     * The `return $panel->…;` statement of panel(): where it ends (after its
     * last call), the indentation of its calls (null: on one line), and the
     * plugin classes already in it (lowercase, as written).
     *
     * @param  list<PhpToken>  $tokens
     * @return array{end: int, indent: ?string, plugins: list<string>}|null
     */
    private function chain(array $tokens): ?array
    {
        $count = count($tokens);
        $body = null;
        foreach ($tokens as $i => $token) {
            if ($token->is(T_FUNCTION) && ($name = $this->next($tokens, $i)) !== null && $tokens[$name]->is(T_STRING) && strtolower($tokens[$name]->text) === 'panel') {
                if ($body !== null) {
                    return null; // two panel() methods: not a file it knows
                }
                for ($j = $name + 1; $j < $count && $tokens[$j]->text !== '{'; $j++) {
                    if ($tokens[$j]->text === ';') {
                        return null; // abstract
                    }
                }
                $body = $j;
                $parameter = null;
                for ($k = $name; $k < $j; $k++) {
                    if ($tokens[$k]->is(T_VARIABLE)) {
                        $parameter ??= $tokens[$k]->text;
                    }
                }
            }
        }
        if ($body === null || $body >= $count || ! isset($parameter)) {
            return null;
        }

        // The one return statement directly in the method's body.
        $return = null;
        $depth = 0;
        for ($i = $body + 1; $i < $count; $i++) {
            $text = $tokens[$i]->text;
            if (in_array($text, ['{', '(', '['], true) || $tokens[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif (in_array($text, ['}', ')', ']'], true)) {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($tokens[$i]->is(T_RETURN)) {
                // One return, directly in the body (not in an if, nor a closure).
                if ($return !== null || $depth !== 0) {
                    return null;
                }
                $return = $i;
            }
        }
        if ($return === null) {
            return null;
        }

        // $panel, then ->method(…) calls, then ;
        $at = $this->next($tokens, $return);
        if ($at === null || ! $tokens[$at]->is(T_VARIABLE) || $tokens[$at]->text !== $parameter) {
            return null;
        }
        $last = $at;
        $indent = null;
        $plugins = [];
        $imports = null;
        while (true) {
            $at = $this->next($tokens, $last);
            if ($at === null) {
                return null;
            }
            if ($tokens[$at]->text === ';') {
                break;
            }
            if (! $tokens[$at]->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
                return null;
            }
            $before = $tokens[$at - 1];
            $indent = $before->is(T_WHITESPACE) && str_contains($before->text, "\n")
                ? substr($before->text, (int) strrpos($before->text, "\n") + 1)
                : $indent;
            $method = $this->next($tokens, $at);
            $open = $method === null ? null : $this->next($tokens, $method);
            if ($method === null || $open === null || ! $tokens[$method]->is(T_STRING) || $tokens[$open]->text !== '(') {
                return null;
            }
            $close = $this->closing($tokens, $open);
            if ($close === null) {
                return null;
            }
            if (strtolower($tokens[$method]->text) === 'plugin') {
                $imports ??= $this->imports($this->codeBefore($tokens, $this->classIndex($tokens) ?? $count));
                $class = $this->pluginClass($tokens, $open, $imports, $this->namespaceOf($tokens));
                if ($class !== null) {
                    $plugins[] = $class;
                }
            }
            $last = $close;
        }

        return ['end' => $tokens[$last]->pos + strlen($tokens[$last]->text), 'indent' => $indent, 'plugins' => $plugins];
    }

    /**
     * The class `->plugin(X::make())` or `->plugin(new X)` names, resolved
     * against the imports, lowercase.
     *
     * @param  list<PhpToken>  $tokens
     * @param  array<string, string>  $imports
     */
    private function pluginClass(array $tokens, int $open, array $imports, ?string $namespace): ?string
    {
        $at = $this->next($tokens, $open);
        if ($at !== null && $tokens[$at]->is(T_NEW)) {
            $at = $this->next($tokens, $at);
        }
        if ($at === null) {
            return null;
        }
        $name = $tokens[$at];
        if ($name->is(T_NAME_FULLY_QUALIFIED)) {
            return strtolower(ltrim($name->text, '\\'));
        }
        if (! $name->is([T_STRING, T_NAME_QUALIFIED])) {
            return null;
        }
        $parts = explode('\\', $name->text, 2);
        $first = strtolower($parts[0]);
        $rest = isset($parts[1]) ? '\\'.$parts[1] : '';
        if (isset($imports[$first])) {
            return strtolower($imports[$first].$rest);
        }

        return strtolower(($namespace === null ? '' : $namespace.'\\').$name->text);
    }

    /**
     * The file's class imports (`use A\B;`, `use A\B as C;`), alias => class, both lowercase.
     *
     * @return array<string, string>
     */
    private function imports(string $code): array
    {
        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $code, $matches, PREG_SET_ORDER);
        $imports = [];
        foreach ($matches as $match) {
            $alias = $match[2] ?? class_basename($match[1]);
            $imports[strtolower($alias)] = strtolower($match[1]);
        }

        return $imports;
    }

    /** The name the file imports the class as, if it does. */
    private function aliasOf(string $code, string $class): ?string
    {
        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (strtolower($match[1]) === strtolower($class)) {
                return $match[2] ?? class_basename($match[1]);
            }
        }

        return null;
    }

    /** The code with `use $class;` among its imports, in order (as Pint sorts them), or after the namespace. */
    private function addImport(string $code, string $class): string
    {
        $tokens = $this->tokens($code) ?? [];
        $classAt = $this->classOffset($tokens) ?? strlen($code);
        $line = "use {$class};\n";
        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+\w+)?\s*;[ \t]*\n/mi', substr($code, 0, $classAt), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($matches === []) {
            if (preg_match('/^namespace\s+[\w\\\\]+\s*;[ \t]*\n/m', $code, $namespace, PREG_OFFSET_CAPTURE) !== 1) {
                return $code;
            }
            $at = $namespace[0][1] + strlen($namespace[0][0]);

            return substr($code, 0, $at)."\n".rtrim($line, "\n").(str_starts_with(substr($code, $at), "\n") ? "\n" : "\n\n").substr($code, $at);
        }
        $sortKey = fn (string $name) => str_replace('\\', ' ', strtolower($name));
        foreach ($matches as $match) {
            if (strcmp($sortKey($match[1][0]), $sortKey($class)) > 0) {
                return substr($code, 0, $match[0][1]).$line.substr($code, $match[0][1]);
            }
        }
        $last = end($matches);
        $at = $last[0][1] + strlen($last[0][0]);

        return substr($code, 0, $at).$line.substr($code, $at);
    }

    /**
     * The `class` of the file's class declaration (not `X::class`, not `new class`).
     *
     * @param  list<PhpToken>  $tokens
     */
    private function classIndex(array $tokens): ?int
    {
        $previous = null;
        foreach ($tokens as $i => $token) {
            if ($token->is(T_CLASS) && ($previous === null || ! $previous->is([T_DOUBLE_COLON, T_NEW]))) {
                return $i;
            }
            if (! $token->is(self::IGNORED)) {
                $previous = $token;
            }
        }

        return null;
    }

    /** @param list<PhpToken> $tokens */
    private function classOffset(array $tokens): ?int
    {
        $index = $this->classIndex($tokens);

        return $index === null ? null : $tokens[$index]->pos;
    }

    /** @param list<PhpToken> $tokens */
    private function className(array $tokens): ?string
    {
        $index = $this->classIndex($tokens);
        $name = $index === null ? null : $this->next($tokens, $index);

        return $name === null ? null : $tokens[$name]->text;
    }

    /** @param list<PhpToken> $tokens */
    private function namespaceOf(array $tokens): ?string
    {
        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE) && ($name = $this->next($tokens, $i)) !== null && $tokens[$name]->is([T_STRING, T_NAME_QUALIFIED])) {
                return $tokens[$name]->text;
            }
        }

        return null;
    }

    /** @param list<PhpToken> $tokens */
    private function codeBefore(array $tokens, int $index): string
    {
        return implode('', array_map(fn (PhpToken $token) => $token->text, array_slice($tokens, 0, $index)));
    }

    /**
     * The next token that isn't whitespace or a comment.
     *
     * @param  list<PhpToken>  $tokens
     */
    private function next(array $tokens, int $i): ?int
    {
        for ($i++; $i < count($tokens); $i++) {
            if (! $tokens[$i]->is(self::IGNORED)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The `)` that closes the `(` at $open.
     *
     * @param  list<PhpToken>  $tokens
     */
    private function closing(array $tokens, int $open): ?int
    {
        $depth = 0;
        for ($i = $open; $i < count($tokens); $i++) {
            $text = $tokens[$i]->text;
            if (in_array($text, ['(', '[', '{'], true) || $tokens[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;
                if ($depth === 0) {
                    return $text === ')' ? $i : null;
                }
            }
        }

        return null;
    }
}
