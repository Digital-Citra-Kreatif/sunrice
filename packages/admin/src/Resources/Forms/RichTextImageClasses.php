<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The classes the rich text editor puts on an image for its width and
 * alignment presets, and the sanitiser rule that keeps only them:
 *
 *     <img class="sunrice-img sunrice-img-w-50 sunrice-img-left" …>
 *
 * Widths: sunrice-img-w-25, -w-50, -w-75, -w-100. Alignment: sunrice-img-left
 * and sunrice-img-right (floated, text wraps), sunrice-img-center and
 * sunrice-img-full. Any other class on an <img> is dropped.
 *
 *     $config = (new HtmlSanitizerConfig)
 *         ->allowElement('img', ['src', 'alt', 'class'])
 *         ->withAttributeSanitizer(new RichTextImageClasses);
 *
 * @api
 */
final class RichTextImageClasses implements AttributeSanitizerInterface
{
    public const WIDTHS = ['25', '50', '75', '100'];

    public const ALIGNMENTS = ['left', 'center', 'right', 'full'];

    /** @return list<string> Every class the editor may put on an image. */
    public static function allowed(): array
    {
        return [
            'sunrice-img',
            ...array_map(fn (string $width): string => "sunrice-img-w-{$width}", self::WIDTHS),
            ...array_map(fn (string $align): string => "sunrice-img-{$align}", self::ALIGNMENTS),
        ];
    }

    /** The allowed classes of a class attribute, or null when none is left. */
    public static function filter(string $classes): ?string
    {
        $allowed = self::allowed();
        $kept = array_values(array_unique(array_filter(
            preg_split('/\s+/', trim($classes)) ?: [],
            fn (string $class): bool => in_array($class, $allowed, true),
        )));

        return $kept === [] ? null : implode(' ', $kept);
    }

    public function getSupportedElements(): array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return self::filter($value);
    }
}
