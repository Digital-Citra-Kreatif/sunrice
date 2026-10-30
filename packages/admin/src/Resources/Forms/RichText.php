<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Formatted text (HTML) written in the panel's rich text editor: headings,
 * lists, quotes, code, links and, unless ->images(false), images from the
 * media library (they keep `data-media-id`, and the width and alignment
 * classes of RichTextImageClasses: `sunrice-img sunrice-img-w-50
 * sunrice-img-left`, and the image styles of config
 * `sunrice-admin.rich_text.image_classes`; any other class is dropped).
 * A captioned image is kept as `<figure class="…"><img …><figcaption>…
 * </figcaption></figure>`, the figure's class held to the same classes and
 * the caption to plain text and the inline formatting (bold, italic, links…).
 *
 * The HTML is sanitised when it's saved: only that formatting is kept, so
 * no script, style or event handler is stored; links may be http(s),
 * mailto, tel or relative, and keep the editor's rel choices (nofollow,
 * sponsored, ugc) and data-link, always with rel="noopener noreferrer"
 * (RichTextLinkAttributes). Still print it unescaped only where you would
 * trust its authors' text.
 *
 *     RichText::make('description')->maxLength(20_000);
 *
 * @api
 */
final class RichText extends Field
{
    public const MAX_LENGTH = 500_000;

    private int $maxLength = self::MAX_LENGTH;

    private bool $images = true;

    private static ?HtmlSanitizer $sanitizer = null;

    public function type(): string
    {
        return 'richtext';
    }

    /** At most this many characters of HTML. */
    public function maxLength(int $length): self
    {
        $this->maxLength = max(1, min(self::MAX_LENGTH, $length));

        return $this;
    }

    /** Offer images from the media library (on by default; it needs the panel's media library). */
    public function images(bool $images = true): self
    {
        $this->images = $images;

        return $this;
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}"];
    }

    public function dehydrate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $html = trim(self::sanitize($value));

        return $html === '' || $html === '<p></p>' ? null : $html;
    }

    /** The HTML with only the editor's formatting kept. */
    public static function sanitize(string $html): string
    {
        return $html === '' ? '' : RichTextLinkAttributes::secure(self::sanitizer()->sanitize($html));
    }

    protected function props(): array
    {
        return [
            'maxLength' => $this->maxLength,
            'images' => $this->images,
            'imageStyles' => $this->images ? RichTextImageClasses::options() : [],
        ];
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }
        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href', 'title', 'target', 'rel', 'data-link'])
            ->withAttributeSanitizer(new RichTextLinkAttributes)
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'data-media-id', 'class'])
            ->allowElement('figure', ['class'])
            ->withAttributeSanitizer(new RichTextImageClasses)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(self::MAX_LENGTH);
        foreach (['p', 'br', 'hr', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'figcaption'] as $element) {
            $config = $config->allowElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
