<?php

declare(strict_types=1);

namespace Sunrice\Admin\Help;

use InvalidArgumentException;

/**
 * Pages of the panel's Help, for the people who use it: the Markdown
 * files in a folder ("writing-entries.md"), shown in name order under a
 * heading. Each page's title is its first "# " line.
 *
 *     $panel->help(HelpSection::make('shop', 'Shop', __DIR__.'/../guide'));
 *
 * @api
 */
final class HelpSection
{
    private function __construct(public readonly string $key, public readonly string $title, public readonly string $directory, public readonly int $sort) {}

    public static function make(string $key, string $title, string $directory, int $sort = 100): self
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Help section \"{$key}\": use a lowercase key like \"shop\".");
        }

        return new self($key, $title, rtrim($directory, '/'), $sort);
    }

    /**
     * Its pages, by name ("writing-entries"), with their titles.
     *
     * @return array<string, string>
     */
    public function pages(): array
    {
        $pages = [];
        foreach (glob($this->directory.'/*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1) {
                $pages[preg_replace('/^\d+-/', '', $slug) ?? $slug] = self::title((string) file_get_contents($file), $slug);
            }
        }

        return $pages;
    }

    /** A page's Markdown, by its name; null when there's no such page. */
    public function markdown(string $page): ?string
    {
        foreach (glob($this->directory.'/*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            if ($page === (preg_replace('/^\d+-/', '', $slug) ?? $slug)) {
                return (string) file_get_contents($file);
            }
        }

        return null;
    }

    private static function title(string $markdown, string $slug): string
    {
        return preg_match('/^# (.+)$/m', $markdown, $match) === 1 ? trim($match[1]) : ucfirst(str_replace('-', ' ', $slug));
    }
}
