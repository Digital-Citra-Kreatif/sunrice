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
 * sunrice-img-full.
 *
 * Plus the image styles a developer lists in config, which editors turn on
 * and off in the image's toolbar (several at once):
 *
 *     // config/sunrice-admin.php
 *     'rich_text' => ['image_classes' => ['rounded' => 'Rounded', 'shadow' => 'Shadow']],
 *
 * Any other class on an <img> is dropped. The config is read each time, so
 * a style taken out of it is dropped the next time the text is saved.
 *
 * A captioned image is a <figure> whose class holds the presets (so the
 * caption floats and sizes with the picture) and whose <img> holds the
 * styles; the same rule keeps only these classes on the <figure>:
 *
 *     <figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img class="rounded" …><figcaption>…</figcaption></figure>
 *
 *     $config = (new HtmlSanitizerConfig)
 *         ->allowElement('img', ['src', 'alt', 'class'])
 *         ->allowElement('figure', ['class'])
 *         ->withAttributeSanitizer(new RichTextImageClasses);
 *
 * @api
 */
final class RichTextImageClasses implements AttributeSanitizerInterface
{
    public const WIDTHS = ['25', '50', '75', '100'];

    public const ALIGNMENTS = ['left', 'center', 'right', 'full'];

    /** What a configured image style's class must look like. */
    public const CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

    /** @return list<string> Every class the editor may put on an image or a captioned image's figure: the presets' and the configured styles'. */
    public static function allowed(): array
    {
        return [
            'sunrice-img',
            ...array_map(fn (string $width): string => "sunrice-img-w-{$width}", self::WIDTHS),
            ...array_map(fn (string $align): string => "sunrice-img-{$align}", self::ALIGNMENTS),
            ...array_keys(self::styles()),
        ];
    }

    /**
     * The image styles in config `sunrice-admin.rich_text.image_classes`,
     * class => label, in their order. A key that isn't a CSS class name
     * (CLASS_PATTERN) or starts with `sunrice-img` (the presets') is
     * ignored; a list of classes uses each class as its label.
     *
     * @return array<string, string>
     */
    public static function styles(): array
    {
        $configured = config('sunrice-admin.rich_text.image_classes', []);
        if (! is_array($configured)) {
            return [];
        }
        $styles = [];
        foreach ($configured as $class => $label) {
            if (is_int($class) && is_string($label)) {
                $class = $label;
            }
            if (! is_string($class) || preg_match(self::CLASS_PATTERN, $class) !== 1 || str_starts_with($class, 'sunrice-img')) {
                continue;
            }
            $styles[$class] = is_string($label) && trim($label) !== '' ? $label : $class;
        }

        return $styles;
    }

    /**
     * The styles as the editor gets them (its `imageStyles` prop), labels
     * translated.
     *
     * @return list<array{class: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::styles() as $class => $label) {
            $translated = __($label);
            $options[] = ['class' => $class, 'label' => is_string($translated) ? $translated : $label];
        }

        return $options;
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
        return ['img', 'figure'];
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
