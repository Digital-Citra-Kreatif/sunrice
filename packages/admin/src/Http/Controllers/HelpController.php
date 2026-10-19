<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Help\HelpSection;
use Sunrice\Admin\Panel;

/**
 * Help: the guide pages the panel and its plugins register, for everyone
 * who can open the panel.
 *
 * @internal
 */
final class HelpController
{
    public function __construct(private Panel $panel) {}

    public function index(): RedirectResponse
    {
        foreach ($this->panel->getHelp() as $section) {
            foreach (array_keys($section->pages()) as $page) {
                return redirect()->route('sunrice.admin.help.show', [$section->key, $page]);
            }
        }
        abort(404);
    }

    public function show(string $section, string $page): Response
    {
        $sections = $this->panel->getHelp();
        $current = array_values(array_filter($sections, fn (HelpSection $s): bool => $s->key === $section))[0] ?? null;
        $markdown = $current?->markdown($page);
        abort_if($markdown === null, 404);

        return Inertia::render('Help/Show', [
            'sections' => array_map(fn (HelpSection $s): array => [
                'key' => $s->key, 'title' => $s->title,
                'pages' => array_map(fn (string $slug, string $title): array => ['slug' => $slug, 'title' => $title], array_keys($s->pages()), $s->pages()),
            ], $sections),
            'section' => $section,
            'page' => $page,
            // The guide's own files, still without their HTML: Markdown only.
            'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ]);
    }
}
