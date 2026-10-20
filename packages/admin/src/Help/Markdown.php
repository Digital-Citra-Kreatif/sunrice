<?php

declare(strict_types=1);

namespace Sunrice\Admin\Help;

use Closure;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Renders a help page (developer docs, user guide) from Markdown to HTML
 * that is safe to print: GitHub-flavoured Markdown with raw HTML escaped,
 * then sanitised, then links and images pointed at the panel's pages.
 *
 *   > [!TIP] / [!NOTE] / [!WARNING] / [!IMPORTANT] / [!CAUTION]   callouts
 *   > [!TECHNICAL]                                               "For developers"
 *   [Entries](entries.md#drafts)   another page, resolved by the caller
 *   ![Alt](images/menu.webp)       an image, resolved by the caller
 *
 * @internal
 */
final class Markdown
{
    /** Callout kind => its label per language (English when the language has none). */
    public const CALLOUTS = [
        'tip' => ['en' => 'Tip', 'id' => 'Tips'],
        'note' => ['en' => 'Note', 'id' => 'Catatan'],
        'important' => ['en' => 'Important', 'id' => 'Penting'],
        'warning' => ['en' => 'Careful', 'id' => 'Perhatian'],
        'caution' => ['en' => 'Caution', 'id' => 'Hati-hati'],
        'technical' => ['en' => 'For developers', 'id' => 'Untuk developer'],
    ];

    /** Pages are small; anything bigger isn't a help page. */
    private const MAX_LENGTH = 1_000_000;

    private static ?HtmlSanitizer $sanitizer = null;

    /** A page's "# Title", outside code blocks. */
    public static function title(string $markdown): ?string
    {
        $markdown = self::withoutCode($markdown);

        return preg_match('/^#\s+(.+?)\s*#*\s*$/m', $markdown, $m) === 1 ? trim(str_replace('`', '', $m[1])) : null;
    }

    /**
     * The page as HTML (without its "# Title", which the page header
     * shows) and its "##" and "###" headings for "On this page".
     *
     * @param  Closure(string): ?string  $link  a link's address as written => the address to use, or null to keep only its text
     * @param  Closure(string): ?string  $image  an image's address as written => the address to use, or null to leave it out
     * @return array{html: string, headings: list<array{id: string, text: string, level: int}>}
     */
    public static function render(string $markdown, Closure $link, Closure $image, string $locale = 'en'): array
    {
        // The first "# Title" line goes; the page header shows it.
        $markdown = (string) preg_replace('/\A(\s*<!--.*?-->)*\s*#\s+[^\n]*\n/s', '', $markdown, 1);

        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 50]);
        $html = self::sanitizer()->sanitize($html);
        $html = self::callouts($html, $locale);
        $html = self::links($html, $link);
        $html = self::images($html, $image);

        return self::headings($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }
        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt', 'title'])
            ->allowElement('code', ['class'])
            ->allowElement('th', ['align', 'colspan', 'rowspan'])
            ->allowElement('td', ['align', 'colspan', 'rowspan'])
            ->allowElement('ol', ['start'])
            // docs: and guide: link to the other set of pages (the callers resolve them).
            ->allowLinkSchemes(['http', 'https', 'mailto', 'docs', 'guide'])
            ->allowMediaSchemes(['https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(self::MAX_LENGTH);
        foreach (['p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'del', 'sub', 'sup', 'kbd', 'pre', 'blockquote', 'ul', 'li', 'table', 'thead', 'tbody', 'tr', 'dl', 'dt', 'dd'] as $element) {
            $config = $config->allowElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }

    /** "> [!TIP] …" blockquotes as labelled boxes. */
    private static function callouts(string $html, string $locale): string
    {
        return (string) preg_replace_callback(
            '#<blockquote>\s*<p>\[!(TIP|NOTE|IMPORTANT|WARNING|CAUTION|TECHNICAL)\]\s*(.*?)</blockquote>#s',
            function (array $m) use ($locale): string {
                $kind = strtolower($m[1]);
                $label = self::CALLOUTS[$kind][$locale] ?? self::CALLOUTS[$kind]['en'];
                // The marker alone on its line leaves an empty paragraph behind.
                $body = (string) preg_replace('#^<p>\s*(<br\s*/?>\s*)?</p>\s*#', '', '<p>'.ltrim((string) preg_replace('#^<br\s*/?>#', '', ltrim($m[2]))));

                return '<aside class="sunrice-callout sunrice-callout-'.$kind.'" role="note">'
                    .'<p class="sunrice-callout-title">'.e($label).'</p>'.trim($body).'</aside>';
            },
            $html,
        );
    }

    /** @param Closure(string): ?string $resolve */
    private static function links(string $html, Closure $resolve): string
    {
        return (string) preg_replace_callback('#<a href="([^"]*)"([^>]*)>(.*?)</a>#s', function (array $m) use ($resolve): string {
            $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if (str_starts_with($href, '#')) {
                return $m[0];
            }
            if (preg_match('#^(https?|mailto):#i', $href) === 1) {
                $external = preg_match('#^https?:#i', $href) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '';

                return '<a href="'.e($href).'"'.$m[2].$external.'>'.$m[3].'</a>';
            }
            $url = $resolve($href);

            return $url === null ? $m[3] : '<a href="'.e($url).'"'.$m[2].'>'.$m[3].'</a>';
        }, $html);
    }

    /** @param Closure(string): ?string $resolve */
    private static function images(string $html, Closure $resolve): string
    {
        return (string) preg_replace_callback('#<img src="([^"]*)"([^>]*?)\s*/?>#', function (array $m) use ($resolve): string {
            $url = $resolve(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            if ($url === null) {
                return '';
            }
            $url = e($url);

            // Opens full size in a new tab.
            return '<a href="'.$url.'" target="_blank" rel="noopener" class="sunrice-shot"><img src="'.$url.'"'.$m[2].' loading="lazy" /></a>';
        }, $html);
    }

    /**
     * Gives "##" and "###" headings ids and lists them.
     *
     * @return array{html: string, headings: list<array{id: string, text: string, level: int}>}
     */
    private static function headings(string $html): array
    {
        $headings = [];
        $used = [];
        $html = (string) preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function (array $m) use (&$headings, &$used): string {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $id = Str::slug($text) ?: 'section';
            $base = $id;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = "{$base}-{$n}";
            }
            $used[$id] = true;
            $headings[] = ['id' => $id, 'text' => $text, 'level' => (int) $m[1]];

            return '<h'.$m[1].' id="'.$id.'">'.$m[2].'</h'.$m[1].'>';
        }, $html);

        return ['html' => $html, 'headings' => $headings];
    }

    private static function withoutCode(string $markdown): string
    {
        return (string) preg_replace('/^(```|~~~).*?^\1/ms', '', $markdown);
    }
}
