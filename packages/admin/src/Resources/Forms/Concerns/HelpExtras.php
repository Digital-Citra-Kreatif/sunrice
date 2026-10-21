<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Link;
use Sunrice\Admin\Support\Say;

/**
 * What a field can say besides its help: values from the form in the
 * help as it's edited, a line for developers, a disclosure with more, and
 * links under it.
 *
 * @internal used by Field, whose methods are the API
 */
trait HelpExtras
{
    /** @var array<string, array{field: string, fallback: string|null}> */
    private array $helpValues = [];

    /** @var array{text: string, example: string|null}|null */
    private ?array $developerHelp = null;

    /** @var array{summary: string, text: string}|null */
    private ?array $helpDetails = null;

    /** @var list<Link>|(Closure(?Model): list<Link>) */
    private array|Closure $links = [];

    /**
     * Placeholders in the help filled from the form as it's edited:
     * ->help("Its page is :url.")->helpValues(['url' => ['route', '/{handle}']])
     * says the route field's value, or while that's empty the fallback with
     * `{name}` as the field `name`'s value.
     *
     * @param  array<string, array{0: string, 1?: string}>  $values  placeholder => [field, fallback]
     */
    public function helpValues(array $values): static
    {
        $this->helpValues = array_map(fn (array $value): array => ['field' => $value[0], 'fallback' => $value[1] ?? null], $values);

        return $this;
    }

    /**
     * A line under the help for developers, "For developers: …", with
     * `backticks` shown as code and `:value` as the field's value as it's
     * typed (`$example` while it's empty):
     * ->developerHelp('In a template: `<x-form handle=":value" />`', 'contact').
     */
    public function developerHelp(?string $text, ?string $example = null): static
    {
        $this->developerHelp = $text === null ? null : ['text' => $text, 'example' => $example];

        return $this;
    }

    /** A disclosure under the help ("Which file?") that opens to more text. */
    public function helpDetails(?string $summary, ?string $text = null): static
    {
        $this->helpDetails = $summary === null || $text === null ? null : ['summary' => $summary, 'text' => $text];

        return $this;
    }

    /**
     * Links under the field (Link): ->links([Link::make('Edit',
     * url('/admin/blueprints/{blueprint}/edit'))->showWhen('blueprint')]).
     * A closure gets the record (null when creating).
     *
     * @param  list<Link>|(Closure(?Model): list<Link>)  $links
     */
    public function links(array|Closure $links): static
    {
        $this->links = $links;

        return $this;
    }

    /** @return array<string, mixed> */
    protected function helpExtras(?Model $record): array
    {
        $links = $this->links instanceof Closure ? ($this->links)($record?->exists === true ? $record : null) : $this->links;
        $developer = $this->developerHelp;
        $details = $this->helpDetails;

        return array_filter([
            'helpValues' => $this->helpValues,
            'developerHelp' => $developer === null ? null : ['text' => Say::it($developer['text']), 'example' => $developer['example']],
            'helpDetails' => $details === null ? null : ['summary' => Say::it($details['summary']), 'text' => Say::it($details['text'])],
            'links' => array_map(fn (Link $link): array => $link->toArray(), $links),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
