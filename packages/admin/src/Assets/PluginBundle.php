<?php

declare(strict_types=1);

namespace Sunrice\Admin\Assets;

use InvalidArgumentException;

/**
 * A plugin's admin JavaScript: an ES module, built with
 *
 * @imajiner/admin-sdk, that registers the plugin's React pages. The panel
 * loads it only when a page under one of its prefixes is opened.
 *
 *     PluginBundle::make('shop')
 *         ->publishedFrom(dirname(__DIR__).'/dist', public_path('vendor/shop'))
 *         ->module('admin.js')
 *         ->style('admin.css')
 *         ->pages('Shop/');
 *
 * Files named relative to publishedFrom() are served from the published
 * folder, with a `?v=` the panel takes from the file the browser gets (the
 * published copy, else the build), looked at when a page needs it, not at
 * boot. A full URL (`asset('vendor/shop/admin.js')`, a CDN's) is used as is.
 *
 * @api
 */
final class PluginBundle
{
    private ?string $module = null;

    /** @var list<string> */
    private array $styles = [];

    /** @var list<string> */
    private array $pages = [];

    private ?string $source = null;

    private ?string $published = null;

    private ?string $name = null;

    /** The published folder's URL (publishedFrom()'s, or asset() of its path under public/). */
    private ?string $publishedUrl = null;

    private function __construct(public readonly string $id) {}

    public static function make(string $id): self
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException("Bundle \"{$id}\": use lowercase letters, numbers and dashes.");
        }

        return new self($id);
    }

    /**
     * The ES module: a file of the published folder ("admin.js", with
     * publishedFrom(), versioned by the panel), or a full URL used as is.
     */
    public function module(string $file): self
    {
        $this->module = $this->checked($file);

        return $this;
    }

    /** A stylesheet loaded with the module: a file of the published folder ("admin.css"), or a full URL. */
    public function style(string $file): self
    {
        $this->styles[] = $this->checked($file);

        return $this;
    }

    /**
     * The Inertia page names the bundle provides, by prefix: "Shop/"
     * covers Inertia::render('Shop/Orders/Show').
     */
    public function pages(string ...$prefixes): self
    {
        foreach ($prefixes as $prefix) {
            if (preg_match('#^[A-Z][A-Za-z0-9]*(?:/[A-Z][A-Za-z0-9]*)*/$#', $prefix) !== 1) {
                throw new InvalidArgumentException("Bundle \"{$this->id}\": page prefixes look like \"Shop/\" (a folder of pages, ending in a slash), not \"{$prefix}\".");
            }
            $this->pages[] = $prefix;
        }

        return $this;
    }

    /** The name admins know the plugin by ("Sunrice CMS"), in messages about its bundle. */
    public function named(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** The bundle's name for people: named()'s, else its id. */
    public function displayName(): string
    {
        return $this->name ?? $this->id;
    }

    /**
     * Where the package's built files are and the folder the app serves
     * them from: `php artisan sunrice:assets` copies them there, files
     * named relative to it (module('admin.js')) are versioned by the copy
     * served, and super admins are warned when it's older than the build.
     * A folder outside public/ needs its URL.
     *
     *     ->publishedFrom(dirname(__DIR__).'/dist', public_path('vendor/shop'))
     */
    public function publishedFrom(string $source, string $published, ?string $url = null): self
    {
        $this->source = rtrim($source, '/');
        $this->published = rtrim($published, '/');
        $this->publishedUrl = $url === null ? null : rtrim($url, '/');
        if ($url === null) {
            $public = rtrim(public_path(), '/').'/';
            if (str_starts_with($this->published.'/', $public)) {
                $this->publishedUrl = substr($this->published, strlen($public));
            }
        }

        return $this;
    }

    /** The folder it's published to (publishedFrom()), if it said. */
    public function publishedPath(): ?string
    {
        return $this->published;
    }

    /** The package's built files (publishedFrom()), if it said. */
    public function sourcePath(): ?string
    {
        return $this->source;
    }

    /**
     * Whether the published files are older than the package's build: one
     * missing, of another size, or older and with other content (chunks in
     * folders included). A stat of each file; the content is read only
     * for a copy older than its build of the same size.
     */
    public function outdated(): bool
    {
        if ($this->source === null || $this->published === null || ! is_dir($this->source)) {
            return false;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->source, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $built) {
            if (! $built instanceof \SplFileInfo || ! $built->isFile()) {
                continue;
            }
            $copy = $this->published.substr($built->getPathname(), strlen($this->source));
            clearstatcache(true, $copy);
            if (! is_file($copy) || filesize($copy) !== $built->getSize()
                || (filemtime($copy) < $built->getMTime() && md5_file($copy) !== md5_file($built->getPathname()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * What's wrong with how it's set up, for Panel::bundle(), or null.
     *
     * @internal
     */
    public function misconfiguration(): ?string
    {
        if ($this->module === null) {
            return "The bundle \"{$this->id}\" has no module: set one with ->module('admin.js') and ->publishedFrom(), or ->module(url).";
        }
        foreach ([$this->module, ...$this->styles] as $file) {
            if (self::relative($file) && $this->published === null) {
                return "The bundle \"{$this->id}\" names \"{$file}\" in its published folder but doesn't say where that is: add ->publishedFrom(\$dist, public_path('vendor/{$this->id}')), or give a full URL.";
            }
            if (self::relative($file) && $this->publishedUrl === null) {
                return "The bundle \"{$this->id}\" is published to {$this->published}, outside public/: give its URL as publishedFrom()'s third argument.";
            }
        }

        return null;
    }

    /** The module's URL, versioned by the file served when it's a file of the published folder. */
    public function getModule(): ?string
    {
        return $this->module === null ? null : $this->url($this->module);
    }

    /** @return list<string> the stylesheets' URLs, versioned likewise */
    public function getStyles(): array
    {
        return array_map(fn (string $style): string => $this->url($style), $this->styles);
    }

    /**
     * The bundle's version, for the panel's Inertia version: changes with
     * its URLs, so with each file of the published folder it names.
     */
    public function version(): string
    {
        return substr(md5(implode("\n", [(string) $this->getModule(), ...$this->getStyles()])), 0, 12);
    }

    /** @return list<string> */
    public function getPages(): array
    {
        return $this->pages;
    }

    /** @return array{id: string, module: string|null, styles: list<string>, pages: list<string>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'module' => $this->getModule(), 'styles' => $this->getStyles(), 'pages' => $this->pages];
    }

    /** A file of the published folder ("admin.js", "chunks/x.css"), not a URL. */
    private static function relative(string $file): bool
    {
        return preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $file) !== 1;
    }

    private function checked(string $file): string
    {
        if (self::relative($file) && preg_match('#^(?!\.\.?/)[A-Za-z0-9_-][A-Za-z0-9._-]*(?:/(?!\.\.?/)[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#', $file) !== 1) {
            throw new InvalidArgumentException("Bundle \"{$this->id}\": \"{$file}\" is neither a file of the published folder (\"admin.js\") nor a URL.");
        }

        return $file;
    }

    private function url(string $file): string
    {
        if (! self::relative($file) || $this->published === null || $this->publishedUrl === null) {
            return $file;
        }
        $base = preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $this->publishedUrl) === 1 || str_starts_with($this->publishedUrl, '/')
            ? $this->publishedUrl.'/'.$file
            : asset($this->publishedUrl.'/'.$file);
        $paths = [$this->published.'/'.$file];
        if ($this->source !== null) {
            $paths[] = $this->source.'/'.$file;
        }
        $version = app(AssetVersions::class)->first(...$paths);

        return $version === null ? $base : $base.'?v='.substr($version, 0, 12);
    }
}
