<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * What the rich text editor puts on a link besides its address, and the
 * sanitiser rule that keeps only that:
 *
 *     <a href="/about" rel="nofollow noopener noreferrer" target="_blank" data-link="entry:12">
 *
 * `rel`: of what it holds, only the editor's choices (REL_OPTIONS:
 * nofollow, sponsored, ugc), always followed by `noopener noreferrer`, so
 * a link never gives the page it opens a handle on this one. `data-link`:
 * what the link points at on the site, a link source's key and an id
 * (Panel::richTextLinkSource(), LINK_PATTERN), or dropped.
 *
 * A link without a `rel` gets none from an attribute sanitiser, so
 * secure() adds it to the sanitised HTML:
 *
 *     $config = (new HtmlSanitizerConfig)
 *         ->allowElement('a', ['href', 'rel', 'target', 'data-link'])
 *         ->withAttributeSanitizer(new RichTextLinkAttributes);
 *     $html = RichTextLinkAttributes::secure((new HtmlSanitizer($config))->sanitize($html));
 *
 * @api
 */
final class RichTextLinkAttributes implements AttributeSanitizerInterface
{
    /** The rel tokens editors may turn on, as search engines read them. */
    public const REL_OPTIONS = ['nofollow', 'sponsored', 'ugc'];

    /** Always on a link: the page it opens can't reach this one, nor learn where its visitor came from. */
    public const SAFE_REL = 'noopener noreferrer';

    /** A data-link value: a link source's key and an id ("entry:12"). */
    public const LINK_PATTERN = '/^[a-z][a-z0-9_-]*:\d{1,18}$/';

    /** The rel to store: the allowed tokens of $rel, each once, in REL_OPTIONS' order, then SAFE_REL. */
    public static function rel(string $rel): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];
        $kept = array_values(array_filter(self::REL_OPTIONS, fn (string $option): bool => in_array($option, $tokens, true)));

        return implode(' ', [...$kept, self::SAFE_REL]);
    }

    /** A data-link value as it's kept ("entry:12"), or null when it isn't one. */
    public static function link(string $link): ?string
    {
        $link = trim($link);

        return preg_match(self::LINK_PATTERN, $link) === 1 ? $link : null;
    }

    /**
     * Sanitised HTML (symfony/html-sanitizer's output, whose attribute
     * values never hold a quote or `>`) with SAFE_REL on every link that
     * has no rel.
     */
    public static function secure(string $html): string
    {
        if (! str_contains($html, '<a')) {
            return $html;
        }

        return preg_replace_callback('/<a(\s[^>]*)?>/', function (array $tag): string {
            $attributes = $tag[1] ?? '';

            return preg_match('/\srel="/', $attributes) === 1 ? $tag[0] : '<a'.$attributes.' rel="'.self::SAFE_REL.'">';
        }, $html) ?? $html;
    }

    public function getSupportedElements(): array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): array
    {
        return ['rel', 'data-link'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $attribute === 'rel' ? self::rel($value) : self::link($value);
    }
}
