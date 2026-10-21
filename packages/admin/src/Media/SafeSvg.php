<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Whether an SVG is free of anything that runs code. Media is served from
 * the app's own domain, so a script in an SVG opened directly would run
 * with the app's (and the admin's) origin.
 *
 * It refuses rather than cleans: nothing is silently changed, and logos
 * and icons exported from design tools pass.
 *
 * @internal
 */
final class SafeSvg
{
    /** Elements that run script or embed other documents. */
    private const BLOCKED_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener'];

    /** Attributes holding a URL (or a value animated into one), by local name. */
    private const URL_ATTRIBUTES = ['href', 'src', 'from', 'to', 'values', 'by', 'action', 'formaction'];

    /**
     * Its size from the root element: the viewBox's width and height, else
     * numeric width and height attributes (px or none), rounded; nulls
     * when it says neither.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function dimensions(string $svg): array
    {
        if (preg_match('/<svg\b[^>]*>/i', $svg, $root) !== 1) {
            return [null, null];
        }
        $attribute = fn (string $name): ?string => preg_match('/\s'.$name.'\s*=\s*["\']([^"\']*)["\']/i', $root[0], $m) === 1 ? trim($m[1]) : null;
        $box = preg_split('/[\s,]+/', $attribute('viewBox') ?? '') ?: [];
        if (count($box) === 4 && is_numeric($box[2]) && is_numeric($box[3]) && (float) $box[2] > 0 && (float) $box[3] > 0) {
            return [max(1, (int) round((float) $box[2])), max(1, (int) round((float) $box[3]))];
        }
        $width = preg_replace('/px$/i', '', $attribute('width') ?? '');
        $height = preg_replace('/px$/i', '', $attribute('height') ?? '');

        return is_numeric($width) && is_numeric($height) && (float) $width > 0 && (float) $height > 0
            ? [max(1, (int) round((float) $width)), max(1, (int) round((float) $height))]
            : [null, null];
    }

    public static function isSafe(string $svg): bool
    {
        // Entities and processing instructions can smuggle content past the checks.
        if (preg_match('/<!ENTITY|<!DOCTYPE[^>]*\[|<\?xml-stylesheet/i', $svg) === 1) {
            return false;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded || $dom->documentElement === null || strtolower($dom->documentElement->localName ?? '') !== 'svg') {
            return false;
        }

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*') ?: [] as $element) {
            if ($element instanceof DOMElement && in_array(strtolower($element->localName ?? ''), self::BLOCKED_ELEMENTS, true)) {
                return false;
            }
        }
        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }
            $name = strtolower($attribute->localName ?? '');
            $value = $attribute->value;
            if (str_starts_with($name, 'on')) {
                return false; // event handlers: onload, onclick…
            }
            if (in_array($name, self::URL_ATTRIBUTES, true) && self::isScriptUrl($value)) {
                return false;
            }
            if ($name === 'style' && preg_match('/javascript:|expression\s*\(|url\s*\(\s*["\']?\s*(?:javascript|data:text\/html)/i', $value) === 1) {
                return false;
            }
        }
        // <style> elements may hide script URLs too.
        foreach ($xpath->query('//*[local-name()="style"]') ?: [] as $style) {
            if ($style instanceof DOMElement && preg_match('/javascript:|expression\s*\(|@import/i', $style->textContent) === 1) {
                return false;
            }
        }

        return true;
    }

    private static function isScriptUrl(string $value): bool
    {
        // Browsers ignore whitespace and control characters in a scheme.
        $clean = strtolower((string) preg_replace('/[\x00-\x20\x7F]+/', '', $value));

        return str_contains($clean, 'javascript:')
            || str_contains($clean, 'vbscript:')
            || str_starts_with($clean, 'data:text/html')
            || str_starts_with($clean, 'data:image/svg');
    }
}
