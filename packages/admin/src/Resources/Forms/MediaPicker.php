<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Sunrice\Admin\Media\Media;

/**
 * Picks files from the media library (or uploads new ones). Stores the
 * media id, or with ->multiple() a list of ids (cast the attribute to an
 * array, or use an `array` settings property).
 *
 *     MediaPicker::make('image_id')->images();
 *     MediaPicker::make('gallery')->images()->multiple(12);
 *
 * Only existing media of the allowed types is accepted. Choosing needs the
 * "sunrice.media.view" permission, uploading "sunrice.media.upload".
 *
 * @api
 */
final class MediaPicker extends Field
{
    private bool $multiple = false;

    private ?int $max = null;

    /** @var list<string> */
    private array $types = [];

    /** Several files, in order, optionally at most `$max`. */
    public function multiple(?int $max = null): self
    {
        $this->multiple = true;
        $this->max = $max === null ? null : max(1, $max);

        return $this;
    }

    public function images(): self
    {
        return $this->types(['image']);
    }

    /** @param list<string> $types image, video, audio and/or document */
    public function types(array $types): self
    {
        $unknown = array_diff($types, Media::TYPES);
        if ($unknown !== []) {
            throw new InvalidArgumentException("MediaPicker \"{$this->name}\": unknown types ".implode(', ', $unknown).'; use '.implode(', ', Media::TYPES).'.');
        }
        $this->types = $types;

        return $this;
    }

    public function type(): string
    {
        return 'media';
    }

    protected function typeRules(): array
    {
        return $this->multiple
            ? ['array', ...($this->max === null ? [] : ["max:{$this->max}"])]
            : ['integer', $this->existsRule()];
    }

    public function nestedRules(): array
    {
        return $this->multiple ? ["{$this->name}.*" => ['integer', 'distinct', $this->existsRule()]] : [];
    }

    public function formValue(?Model $record): mixed
    {
        return $this->normalise(parent::formValue($record));
    }

    public function fill(Model $record, mixed $value): void
    {
        $record->setAttribute($this->name, $this->normalise($value));
    }

    /**
     * The value as stored: an id or null, or a list of ids.
     *
     * @return int|list<int>|null
     */
    public function normalise(mixed $value): int|array|null
    {
        if (is_string($value) && $this->multiple) {
            $value = json_decode($value, true);
        }
        if (! $this->multiple) {
            return is_numeric($value) ? (int) $value : null;
        }

        return is_array($value) ? array_values(array_map('intval', array_filter($value, 'is_numeric'))) : [];
    }

    protected function props(): array
    {
        return ['multiple' => $this->multiple, 'max' => $this->max, 'types' => $this->types];
    }

    private function existsRule(): object
    {
        $types = $this->types;

        return Rule::exists((new Media)->getTable(), 'id')->where(function (Builder $query) use ($types): void {
            $query->whereNull('deleted_at');
            Media::whereTypes($query, $types);
        });
    }
}
