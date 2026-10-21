<?php

declare(strict_types=1);

use Sunrice\Cms\Models\Submission;
use Tests\Support\LuminaSite;

/*
 * Whole-page cache (SUNRICE_PAGE_CACHE=true, spatie/laravel-responsecache): kept pages are served again, the
 * property filters are part of the key, and forms keep working because each visitor gets their own CSRF token.
 */
beforeEach(function (): void {
    putenv('SUNRICE_PAGE_CACHE=true');
    $_ENV['SUNRICE_PAGE_CACHE'] = $_SERVER['SUNRICE_PAGE_CACHE'] = 'true';
    putenv('APP_DEBUG=true');
    $this->refreshApplication();
    $this->withoutVite();
    $this->artisan('migrate:fresh');
    $this->site = LuminaSite::build();
});

afterEach(function (): void {
    putenv('SUNRICE_PAGE_CACHE=false');
    $_ENV['SUNRICE_PAGE_CACHE'] = $_SERVER['SUNRICE_PAGE_CACHE'] = 'false';
    putenv('APP_DEBUG');
});

it('keeps pages and serves them again', function (): void {
    expect(config('sunrice-cms.cache.pages'))->toBeTrue();
    $this->get('/properties/villa-samudra')->assertOk();
    $this->get('/properties/villa-samudra')->assertOk()->assertHeader('X-Cache-Status', 'HIT');
});

it('keeps one page per filter', function (): void {
    $this->get('/properties?property_type=villa')->assertOk();
    $this->get('/properties?listing_type=for-rent')->assertOk()
        ->assertSee('Seminyak Pool Villa')->assertDontSee('>Villa Samudra</a>', false);
});

it('keeps the filter values the listing offers, and no copies for others', function (): void {
    foreach (['/properties?bedrooms_min=3', '/properties?price_max=5000000000&sort=price-asc'] as $offered) {
        $this->get($offered)->assertOk();
        $this->get($offered)->assertOk()->assertHeader('X-Cache-Status', 'HIT');
    }
    // Any other number, a filter's parameter on a page without the filter, or a page past the last: not kept.
    foreach (['/properties?price_min=1234567', '/properties?bedrooms_min=9137', '/about?bedrooms_min=5', '/about?sort=zzz'] as $made_up) {
        $this->get($made_up)->assertOk();
        $this->get($made_up)->assertOk()->assertHeader('X-Cache-Status', 'MISS');
    }
    $this->get('/properties?page=123456')->assertNotFound();
    $this->get('/journal?page=55555')->assertNotFound();
});

it('gives every visitor their own CSRF token on a kept page, so forms still submit', function (): void {
    $first = $this->get('/contact')->getContent();
    $this->flushSession();
    app('session')->driver()->regenerateToken();
    $second = $this->get('/contact');
    $second->assertHeader('X-Cache-Status', 'HIT');
    preg_match('/name="_token" value="([^"]+)"/', $first, $a);
    preg_match('/name="_token" value="([^"]+)"/', $second->getContent(), $b);

    expect($b[1] ?? null)->not->toBe($a[1] ?? null)->and($b[1] ?? null)->toBe(session()->token());

    $this->from('/contact')->post(route('sunrice.cms.forms.submit', 'contact'), [
        '_token' => $b[1], 'data' => ['name' => 'Rina', 'email' => 'rina@example.com', 'message' => 'Hello'],
        'locale' => 'en', '_form' => 'contact', ...honeypot(),
    ])->assertRedirect('/contact#form-contact-status');
    expect(Submission::query()->count())->toBe(1);
});
