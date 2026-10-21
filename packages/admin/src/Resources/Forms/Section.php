<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Support\Say;

/**
 * A titled card of fields, laid out in a grid:
 * Section::make('Pricing')->columns(2)->schema([...]).
 *
 * @api
 */
final class Section implements Component
{
    private ?string $description = null;

    private int $columns = 1;

    /** @var list<Field> */
    private array $schema = [];

    /** @var list<Link>|(Closure(?Model): list<Link>) */
    private array|Closure $links = [];

    private function __construct(private string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function columns(int $columns): self
    {
        $this->columns = max(1, min(4, $columns));

        return $this;
    }

    /** @param list<Field> $fields */
    public function schema(array $fields): self
    {
        $this->schema = $fields;

        return $this;
    }

    /**
     * Links in the card's header (Link), like "View on site": a closure
     * gets the record (null when creating), so a link can wait until
     * there's something to link to.
     *
     * @param  list<Link>|(Closure(?Model): list<Link>)  $links
     */
    public function links(array|Closure $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function shownOn(string $operation): bool
    {
        return true;
    }

    public function fields(): array
    {
        return $this->schema;
    }

    /** @return array<string, mixed> */
    public function toArray(?Model $record = null): array
    {
        $links = $this->links instanceof Closure ? ($this->links)($record?->exists === true ? $record : null) : $this->links;

        return ['kind' => 'section', 'title' => Say::it($this->title), 'description' => Say::it($this->description), 'columns' => $this->columns]
            + ($links !== [] ? ['links' => array_map(fn (Link $link): array => $link->toArray(), $links)] : []);
    }
}
