<?php

declare(strict_types=1);

use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

it('renders a property with its facts, price, status and agent', function (): void {
    $this->get('/properties/villa-samudra')->assertOk()
        ->assertSee('<h1 class="h1 mt-5">Villa Samudra</h1>', false)
        ->assertSee('IDR 28,500,000,000')
        ->assertSee('badge-available', false)
        ->assertSee('1,200 m²')
        ->assertSee('Infinity pool')
        ->assertSee('Private pool')
        ->assertSee('Ayu Pratiwi')
        ->assertSee('https://maps.google.com/?q=Canggu', false)
        ->assertSee(url('/neighbourhoods/canggu'), false)
        ->assertSee('<meta name="description" content="A five-bedroom villa above the rice fields of Canggu.">', false);   // SEO from fields: summary
});

it('formats rent with its suffix and Indonesian numbers', function (): void {
    $this->get('/properties/seminyak-pool-villa')->assertSee('IDR 95,000,000')->assertSee('/ month');
    $this->get('/id/properties/vila-samudra')->assertOk()->assertSee('Rp 28.500.000.000')->assertSee('Kolam infinity')->assertSee('Tersedia');
});

it('prefills the inquiry form with the property title', function (): void {
    $this->get('/properties/villa-samudra')
        ->assertSee('<input type="hidden" name="data[property]" value="Villa Samudra">', false)
        ->assertSee(route('sunrice.cms.forms.submit', 'inquiry'), false);
});

it('describes the property as a RealEstateListing with an Offer', function (): void {
    $html = $this->get('/properties/villa-samudra')->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $graph = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [json_decode($json, true)]);
    $listing = $graph->firstWhere('@type', 'RealEstateListing');

    expect($listing)->not->toBeNull()
        ->and($listing['offers']['@type'])->toBe('Offer')
        ->and($listing['offers']['price'])->toEqual(28_500_000_000)
        ->and($listing['offers']['priceCurrency'])->toBe('IDR')
        ->and($listing['about']['numberOfRooms'])->toBe(5);
});

it('shows properties nearby from the same area', function (): void {
    // Canggu and Seminyak are both under Badung.
    $this->get('/properties/villa-samudra')->assertSee('More properties nearby')->assertSee('Seminyak Pool Villa')->assertDontSee('Kemang Garden House');
});

it('lists properties with filters from the address', function (string $query, array $see, array $dontSee): void {
    $response = $this->get('/properties'.$query)->assertOk();
    foreach ($see as $title) {
        $response->assertSee($title);
    }
    foreach ($dontSee as $title) {
        $response->assertDontSee('>'.$title.'</a>', false);
    }
})->with([
    'all' => ['', ['Villa Samudra', 'Seminyak Pool Villa', 'Kemang Garden House', '3 properties'], []],
    'for sale' => ['?listing_type=for-sale', ['Villa Samudra', 'Kemang Garden House'], ['Seminyak Pool Villa']],
    'villas' => ['?property_type=villa', ['Villa Samudra', 'Seminyak Pool Villa'], ['Kemang Garden House']],
    'Bali with the places under it' => ['?location=bali', ['Villa Samudra', 'Seminyak Pool Villa'], ['Kemang Garden House']],
    '4+ bedrooms' => ['?bedrooms_min=4', ['Villa Samudra', 'Kemang Garden House'], ['Seminyak Pool Villa']],
    'up to 20 billion' => ['?price_max=20000000000', ['Seminyak Pool Villa', 'Kemang Garden House'], ['Villa Samudra']],
    'keywords' => ['?q=Kemang', ['Kemang Garden House'], ['Villa Samudra']],
]);

it('sorts by price', function (): void {
    $this->get('/properties?sort=price-asc')->assertSeeInOrder(['Seminyak Pool Villa', 'Kemang Garden House', 'Villa Samudra']);
    $this->get('/properties?sort=price-desc')->assertSeeInOrder(['Villa Samudra', 'Kemang Garden House', 'Seminyak Pool Villa']);
});

it('shows the applied filters with links that remove them', function (): void {
    $this->get('/properties?property_type=villa')->assertSee('Clear all')->assertSee('chip', false);
});

it('renders the term pages, a location with the places under it', function (): void {
    $this->get('/property-type/villa')->assertOk()->assertSee('Villa Samudra')->assertDontSee('Kemang Garden House');
    $this->get('/location/bali')->assertOk()->assertSee('Villa Samudra')->assertSee('Seminyak Pool Villa')->assertDontSee('Kemang Garden House');
    $this->get('/id/property-type/vila')->assertOk()->assertSee('Vila');
});

it('says a sold or let home is gone instead of offering a viewing, and labels the price for what it is', function (): void {
    $house = $this->site->entry('properties/kemang-garden-house');
    app(SaveDraft::class)->handle($house, ['data' => [...$house->draft_data, 'property_status' => 'sold']]);
    app(PublishEntry::class)->handle($house->fresh());

    $this->get('/properties/kemang-garden-house')->assertOk()
        ->assertSee('Last asking price')->assertSee('This home has been sold')->assertSee('See the homes available now')
        ->assertDontSee('Asking price')->assertDontSee(route('sunrice.cms.forms.submit', 'inquiry'), false);
    $this->get('/properties/seminyak-pool-villa')->assertSee('<p class="eyebrow">Rent</p>', false)->assertSee(route('sunrice.cms.forms.submit', 'inquiry'), false);
});

it('states a rent\'s unit in the Offer, and shows short prices on cards', function (): void {
    $html = $this->get('/properties/seminyak-pool-villa')->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $listing = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'RealEstateListing');

    expect($listing['offers']['priceSpecification'])->toMatchArray(['@type' => 'UnitPriceSpecification', 'unitCode' => 'MON', 'price' => 95_000_000]);
    $this->get('/properties')->assertSee('IDR 28.5 billion')->assertDontSee('IDR 28,500,000,000');
});
