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
 *         ->module(asset('vendor/shop/admin.js'))
 *         ->style(asset('vendor/shop/admin.css'))
 *         ->pages('Shop/');
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

    private function __construct(public readonly string $id) {}

    public static function make(string $id): self
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException("Bundle \"{$id}\": use lowercase letters, numbers and dashes.");
        }

        return new self($id);
    }

    /** The URL of the ES module (usually under public/vendor/<plugin>). */
    public function module(string $url): self
    {
        $this->module = $url;

        return $this;
    }

    /** A stylesheet loaded with the module. */
    public function style(string $url): self
    {
        $this->styles[] = $url;

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
     * Where the package's built files are and where the app publishes
     * them (`vendor:publish`): the panel warns admins when the published
     * copy differs from the build, after an update that didn't publish.
     *
     *     ->publishedFrom(__DIR__.'/../dist', public_path('vendor/shop'))
     */
    public function publishedFrom(string $source, string $published): self
    {
        $this->source = rtrim($source, '/');
        $this->published = rtrim($published, '/');

        return $this;
    }

    /** The folder it's published to (publishedFrom()), if it said. */
    public function publishedPath(): ?string
    {
        return $this->published;
    }

    /** Whether the published files differ from the package's build (one missing or changed, chunks in folders included). */
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
            if (! is_file($copy) || filesize($copy) !== $built->getSize() || md5_file($copy) !== md5_file($built->getPathname())) {
                return true;
            }
        }

        return false;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    /** @return list<string> */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /** @return list<string> */
    public function getPages(): array
    {
        return $this->pages;
    }

    /** @return array{id: string, module: string|null, styles: list<string>, pages: list<string>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'module' => $this->module, 'styles' => $this->styles, 'pages' => $this->pages];
    }
}
