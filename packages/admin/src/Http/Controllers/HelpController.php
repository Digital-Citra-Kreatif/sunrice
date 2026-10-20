<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Help\HelpPages;
use Sunrice\Admin\Help\Markdown;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\Branding;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The Help pages: the developer docs ("sunrice.docs.view") and the user
 * guide ("sunrice.guide.view"), Markdown files the panel and its plugins
 * register with Panel::docs() and Panel::guide(), rendered server-side
 * to sanitised HTML.
 *
 * @internal
 */
final class HelpController
{
    /** The reader's guide language, remembered for the next pages. */
    public const SESSION_KEY = 'sunrice.guide_language';

    /** Images a guide may show, from its "images" folder. */
    public const IMAGE_PATTERN = '[a-z0-9_-]+\.(?:webp|png|jpe?g|gif|avif)';

    public function __construct(private Panel $panel) {}

    public function docs(Request $request, ?string $page = null): Response
    {
        $this->authorize($request, 'sunrice.docs.view');
        $docs = new HelpPages($this->panel->getDocs());
        $guide = $this->may($request, 'sunrice.guide.view') ? new HelpPages($this->panel->getGuides(), localized: true) : null;

        return $this->show('Help/Docs', $docs, $page, '', [
            'docs' => fn (string $target): ?string => $docs->path($target) === null ? null : $this->url('docs', $target),
            'guide' => fn (string $target): ?string => $guide?->path($target) === null ? null : $this->url('guide', $target),
        ]);
    }

    public function guide(Request $request, ?string $page = null): Response
    {
        $this->authorize($request, 'sunrice.guide.view');
        $guide = new HelpPages($this->panel->getGuides(), localized: true);
        $docs = $this->may($request, 'sunrice.docs.view') ? new HelpPages($this->panel->getDocs()) : null;
        $language = $this->language($request, $guide->languages());

        return $this->show('Help/Guide', $guide, $page, $language, [
            'guide' => fn (string $target): ?string => $guide->path($target) === null ? null : $this->url('guide', $target),
            'docs' => fn (string $target): ?string => $docs?->path($target) === null ? null : $this->url('docs', $target),
        ], [
            'language' => $language,
            'languages' => array_map(fn (string $code): array => ['code' => $code, 'label' => HelpPages::LANGUAGE_NAMES[$code] ?? strtoupper($code)], $guide->languages()),
        ]);
    }

    /** An image from a guide's "images" folder; nothing outside it can be named. */
    public function image(Request $request, string $section, string $file): BinaryFileResponse
    {
        $this->authorize($request, 'sunrice.guide.view');
        $directory = (new HelpPages($this->panel->getGuides(), localized: true))->directory($section);
        abort_if($directory === null || preg_match('/^'.self::IMAGE_PATTERN.'$/', $file) !== 1, 404);
        $path = realpath($directory.'/images/'.$file);
        abort_unless($path !== false && str_starts_with($path, $directory.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR) && is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * @param  array{docs: \Closure(string): ?string, guide: \Closure(string): ?string}  $other  "docs:" and "guide:" links
     * @param  array<string, mixed>  $extra
     */
    private function show(string $component, HelpPages $pages, ?string $page, string $language, array $other, array $extra = []): Response
    {
        $sections = $pages->sections($language);
        $page ??= $pages->first($language);
        $file = $page === null ? null : $pages->path($page, $language);
        abort_if($page !== null && $file === null, 404);

        $content = ['title' => null, 'html' => '', 'headings' => []];
        if ($file !== null) {
            $markdown = (string) file_get_contents($file['path']);
            $kind = $component === 'Help/Guide' ? 'guide' : 'docs';
            $rendered = Markdown::render(
                $markdown,
                fn (string $href): ?string => $this->link($href, $file['path'], $pages, $kind, $other),
                fn (string $src): ?string => $kind === 'guide' && preg_match('#^(?:\./|\.\./)?images/('.self::IMAGE_PATTERN.')$#', $src, $m) === 1
                    ? $this->url('guide/images', $file['section'].'/'.$m[1]) : null,
                $file['language'] ?: 'en',
            );
            $title = Markdown::title($markdown) ?? $page;
            $content = $kind === 'guide'
                ? ['title' => $this->panelName($title), 'html' => $this->panelName($rendered['html'], html: true), 'headings' => array_map(fn (array $h): array => array_merge($h, ['text' => $this->panelName($h['text'])]), $rendered['headings'])]
                : ['title' => $title, 'html' => $rendered['html'], 'headings' => $rendered['headings']];
        }

        if ($component === 'Help/Guide') {
            $sections = array_map(fn (array $s): array => array_merge($s, ['pages' => array_map(fn (array $p): array => array_merge($p, ['title' => $this->panelName($p['title'])]), $s['pages'])]), $sections);
        }
        $flat = [];
        foreach ($sections as $section) {
            array_push($flat, ...$section['pages']);
        }
        $index = array_search($page, array_column($flat, 'page'), true);

        return Inertia::render($component, [
            'page' => $page,
            ...$content,
            'sections' => $sections,
            'previous' => is_int($index) && $index > 0 ? $flat[$index - 1] : null,
            'next' => is_int($index) ? ($flat[$index + 1] ?? null) : null,
            // The page isn't written in the reader's language: shown in this one instead.
            'shownIn' => $language !== '' && $file !== null && $file['language'] !== $language ? $file['language'] : null,
            ...$extra,
        ]);
    }

    /**
     * Where a link in a page goes: another page of the same set
     * ("site.md#search", "../admin/panel.md"), the other set ("docs:cms/site",
     * "guide:cms/entries"), or nowhere (its text stays).
     *
     * @param  array{docs: \Closure(string): ?string, guide: \Closure(string): ?string}  $other
     */
    private function link(string $href, string $from, HelpPages $pages, string $kind, array $other): ?string
    {
        $target = strstr($href, '#', true);
        $fragment = $target === false ? '' : substr($href, strlen($target) + 1);
        $target = $target === false ? $href : $target;
        $hash = $fragment === '' ? '' : '#'.rawurlencode($fragment);
        if (preg_match('#^(docs|guide):('.HelpPages::PAGE_PATTERN.')(?:\.md)?$#', $target, $m) === 1) {
            $url = $other[$m[1] === 'docs' ? 'docs' : 'guide']($m[2]);

            return $url === null ? null : $url.$hash;
        }
        if (! str_ends_with($target, '.md') || str_contains($target, ':')) {
            return null;
        }
        $page = $pages->pageOf(dirname($from).'/'.$target);

        return $page === null ? null : $this->url($kind, $page).$hash;
    }

    private function url(string $prefix, string $page): string
    {
        return '/'.$this->panel->getPath().'/'.$prefix.'/'.$page;
    }

    /** "{panel}" in the guide reads as the panel's name (Settings → Branding). */
    private function panelName(string $text, bool $html = false): string
    {
        $name = Branding::make()->name();

        return str_replace('{panel}', $html ? e($name) : $name, $text);
    }

    /**
     * The guide's language: ?lang= (then remembered), the remembered one,
     * the app's locale, the browser's, else English.
     *
     * @param  list<string>  $languages
     */
    private function language(Request $request, array $languages): string
    {
        $chosen = $request->query('lang');
        if (is_string($chosen) && in_array($chosen, $languages, true)) {
            $request->session()->put(self::SESSION_KEY, $chosen);

            return $chosen;
        }
        $remembered = $request->session()->get(self::SESSION_KEY);
        if (is_string($remembered) && in_array($remembered, $languages, true)) {
            return $remembered;
        }
        $app = app()->getLocale();
        if (in_array($app, $languages, true) && $app !== 'en') {
            return $app;
        }

        return $languages === [] ? 'en' : ($request->getPreferredLanguage($languages) ?? $languages[0]);
    }

    private function authorize(Request $request, string $permission): void
    {
        abort_unless($this->may($request, $permission), 403);
    }

    private function may(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user instanceof Authorizable && $user->can($permission);
    }
}
