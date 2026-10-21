<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

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

    public function shownOn(string $operation): bool
    {
        return true;
    }

    public function fields(): array
    {
        return $this->schema;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['kind' => 'section', 'title' => Say::it($this->title), 'description' => Say::it($this->description), 'columns' => $this->columns];
    }
}
