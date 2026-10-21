<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\LuminaSite;

/*
 * Queries per page with the site's cache warm (the second visit). The budgets leave a little room above what
 * the pages take today (counts recorded in qa/findings-developer.md); a template that queries in a loop
 * breaks them as soon as there is more than one card.
 */
beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

function queriesFor(string $url): int
{
    test()->get($url)->assertOk();   // warm the site cache
    app()->forgetScopedInstances();
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    test()->get($url)->assertOk();

    return $count;
}

it('stays within its query budget', function (string $url, int $budget): void {
    $count = queriesFor($url);
    fwrite(STDERR, sprintf("  queries %-40s %3d (budget %d)\n", $url, $count, $budget));

    expect($count)->toBeLessThanOrEqual($budget);
})->with([
    'homepage' => ['/', 45],
    'page' => ['/about', 25],
    'sub-page' => ['/our-story', 25],
    'contact' => ['/contact', 25],
    'sell' => ['/sell-with-us', 25],
    'property' => ['/properties/villa-samudra', 58],
    'property (id)' => ['/id/properti/vila-samudra', 48],
    'properties listing' => ['/properties', 44],
    'properties filtered' => ['/properties?location=bali&sort=price-desc', 50],
    'team' => ['/team', 25],
    'agent' => ['/team/ayu-pratiwi', 30],
    'neighbourhood' => ['/neighbourhoods/canggu', 30],
    'journal' => ['/journal', 36],
    'post' => ['/journal/market-report-7', 52],
    'location term' => ['/location/bali', 40],
    'topic term' => ['/journal/topic/market-reports', 38],
    'search' => ['/search?q=villa', 25],
]);
