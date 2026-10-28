<?php

declare(strict_types=1);

namespace Sunrice\Cms\Fields\Types;

use Sunrice\Admin\Resources\Forms\RichTextImageClasses;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\FieldType;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Support\HtmlSanitizer;

/**
 * Formatted text (HTML), cleaned on save: only the formatting the editor
 * can produce is kept, so scripts and event handlers never reach the site.
 * Images from the media library carry data-media-id, and on images only
 * the editor's presets and the configured image styles are kept
 * (the panel's RichTextImageClasses).
 */
final class RichText extends FieldType
{
    public function type(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return 'Rich text';
    }

    public function rules(Field $field): array
    {
        return ['string', 'max:'.HtmlSanitizer::MAX_LENGTH];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        $html = is_string($value) ? HtmlSanitizer::sanitize($value) : '';

        return trim(strip_tags($html, '<img>')) === '' ? null : $html;
    }

    public function references(mixed $value, Field $field): array
    {
        return Reference::many(Reference::MEDIA, is_string($value) ? HtmlSanitizer::mediaIds($value) : []);
    }

    /** The editor also gets the image styles to offer (config `sunrice-admin.rich_text.image_classes`). */
    public function toAdmin(Field $field, Schema $schema): array
    {
        return parent::toAdmin($field, $schema) + ['imageStyles' => RichTextImageClasses::options()];
    }

    public function translatableByDefault(): bool
    {
        return true;
    }
}
