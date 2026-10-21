<?php

declare(strict_types=1);

use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

/** @return list<int> the levels of the page's headings, in order */
function headingLevels(string $html): array
{
    preg_match_all('~<h([1-6])\b~i', $html, $matches);

    return array_map('intval', $matches[1]);
}

it('never skips a heading level on listing and term pages', function (string $path): void {
    $levels = headingLevels($this->get($path)->assertOk()->getContent());

    expect($levels[0] ?? null)->toBe(1);
    foreach (array_slice($levels, 1) as $i => $level) {
        expect($level - $levels[$i])->toBeLessThanOrEqual(1, "{$path}: h{$levels[$i]} is followed by h{$level}");
    }
})->with([
    'properties' => '/properties',
    'team' => '/team',
    'neighbourhoods' => '/neighbourhoods',
    'journal' => '/journal',
    'location term' => '/location/bali',
    'topic term' => '/journal/topic/market-reports',
    'search' => '/search?q=villa',
]);

it('hides the agent photo link, which repeats the name link beside it, from assistive technology', function (): void {
    $this->get('/properties/villa-samudra')->assertOk()
        ->assertSee('class="size-16 shrink-0 overflow-hidden rounded-full" tabindex="-1" aria-hidden="true"', false);
});

it('keeps a submit button on the property filters, which keyboard users submit with', function (): void {
    $this->get('/properties')->assertOk()
        ->assertSee('data-autosubmit', false)
        ->assertSee('<button type="submit" class="btn btn-primary">', false);
});
