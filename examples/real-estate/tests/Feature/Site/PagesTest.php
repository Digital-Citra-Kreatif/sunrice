<?php

declare(strict_types=1);

use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Models\GlobalSet;
use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

it('renders the homepage from the page blueprint sections', function (): void {
    $response = $this->get('/')->assertOk();

    $response->assertSee('<h1 class="display reveal">Find the home that feels like yours</h1>', false)
        ->assertSee('450+')                                // stats
        ->assertSee('Featured homes')->assertSee('Villa Samudra')   // featured (no choice: the "featured" ones)
        ->assertSee('Meet the team')->assertSee('Ayu Pratiwi')      // team
        ->assertSee('They found us a home')                         // testimonials
        ->assertSee('Our approach')                                 // image_text
        ->assertSee('Can foreigners buy property in Indonesia?')    // faq
        ->assertSee('Thinking of selling?')                         // cta
        ->assertSee('Licensed agents since 2006.');                  // rich_text

    // A hero opens the page: body class from the bodyClassUsing hook.
    expect($response->getContent())->toMatch('/<body class="[^"]*\bhas-hero\b/')->toMatch('/<body class="[^"]*\bhome\b/');
});

it('prints the SEO tags and structured data in the head', function (): void {
    $html = $this->get('/')->getContent();

    expect($html)
        ->toContain('<title>Lumina Estates</title>')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('hreflang="id" href="'.url('/id').'"')
        ->toContain('hreflang="x-default"')
        ->toContain('<meta property="og:site_name" content="Lumina Estates">')
        ->toContain('"@type":"FAQPage"')
        ->toContain('"@type":"WebSite"');
});

it('shows the header menu with its children and the active trail', function (): void {
    $html = $this->get('/properties')->assertOk()->getContent();

    expect($html)->toMatch('/<li class="nav-item is-trail">|aria-current="page"[^>]*>\s*Properties/')
        ->toContain('/properties?listing_type=for-sale')
        ->toContain(url('/property-type/villa'));
    $this->get('/about')->assertSee('aria-current="page"', false);
});

it('shows the footer from the globals and the newsletter form', function (): void {
    $this->get('/about')->assertOk()
        ->assertSee('PT Lumina Estates Indonesia')
        ->assertSee('+62 21 555 0100')
        ->assertSee('Market notes, monthly')
        ->assertSee('https://instagram.com/lumina', false)
        ->assertSee(route('sunrice.cms.forms.submit', 'newsletter'), false);
});

it('gives sub-pages breadcrumbs and lists a page\'s sub-pages', function (): void {
    $this->get('/our-story')->assertOk()
        ->assertSeeInOrder(['Home', 'About', 'Our story']);
    $this->get('/about')->assertOk()->assertSee('More in About')->assertSee(url('/our-story'), false);
});

it('serves the Indonesian homepage and globals in Indonesian', function (): void {
    $this->get('/id')->assertOk()
        ->assertSee('<html lang="id">', false)
        ->assertSee('Temukan rumah yang terasa milik Anda')
        ->assertSee('Rumah penuh cahaya')       // company tagline, id
        ->assertSee('Catatan pasar, bulanan')   // footer, id
        ->assertSee('Langsung ke konten');     // lang/id/site.php
});

it('shows the main language with a note on untranslated pages', function (): void {
    $this->get('/id/about')->assertOk()
        ->assertSee('belum tersedia dalam Bahasa Indonesia')
        ->assertSee('<link rel="canonical" href="'.url('/about').'">', false);
});

it('marks the main language\'s words on untranslated pages as English, the page\'s own as Indonesian', function (): void {
    $this->get('/id/about')->assertOk()
        ->assertSee('<html lang="id">', false)
        ->assertSee('<h1 class="h1 reveal mt-3 max-w-4xl" lang="en">About</h1>', false)
        ->assertSee('<p class="lead reveal mt-6 max-w-2xl" lang="en">Who we are.</p>', false);
    $this->get('/id/properties/kemang-garden-house')->assertOk()
        ->assertSee('<h1 class="h1 mt-5" lang="en">Kemang Garden House</h1>', false)
        ->assertSee('lang="en">A family house with a mature garden in Kemang.</p>', false);
    // Translated: nothing to mark.
    $this->get('/id/properties/vila-samudra')->assertOk()->assertSee('<h1 class="h1 mt-5">Vila Samudra</h1>', false);
    $this->get('/about')->assertOk()->assertSee('<h1 class="h1 reveal mt-3 max-w-4xl">About</h1>', false);
});

it('renders the contact page with the company details and the contact form', function (): void {
    $this->get('/contact')->assertOk()
        ->assertSee('Send us a message')
        ->assertSee('name="data[topic]"', false)
        ->assertSee('<option value="buying"', false)
        ->assertSee('Jl. Kemang Raya 8');
});

it('renders the sell page with the CMS-printed valuation form, file input included', function (): void {
    $this->get('/sell-with-us')->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('type="file"', false)
        ->assertSee('Request my valuation');
});

it('shows 404 pages in the site layout, in the address language', function (): void {
    $this->get('/no-such-page')->assertNotFound()->assertSee('We can’t find that page')
        ->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('rel="canonical"', false);
    $this->get('/id/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
});

it('redirects the old addresses', function (): void {
    $this->get('/listings')->assertRedirect(url('/properties'))->assertStatus(301);
    $this->get('/blog')->assertRedirect(url('/journal'))->assertStatus(301);
});

it('lists the site in the sitemap', function (): void {
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(url('/properties/villa-samudra'), false)
        ->assertSee(url('/id/properties/vila-samudra'), false)
        ->assertSee(url('/journal'), false);
});

it('serves robots.txt with the sitemap\'s address, and keeps a hidden site out', function (): void {
    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('sitemap.xml'));
    // On APP_URL, whatever Host the request names.
    config(['app.url' => 'https://lumina.test']);
    $this->withServerVariables(['HTTP_HOST' => 'evil.example'])->get('/robots.txt')
        ->assertSee('Sitemap: https://lumina.test/sitemap.xml')->assertDontSee('evil.example');
    $this->withServerVariables([]);
    config(['sunrice-cms.seo.noindex' => true]);
    $this->get('/robots.txt')->assertSee('Disallow: /')->assertDontSee('Sitemap:');
});

it('prints the copyright line once in the footer, with the legal text the content seeds', function (): void {
    $content = json_decode((string) file_get_contents(database_path('content.json')), true);
    $footer = collect($content['globals'] ?? [])->firstWhere('handle', 'footer');
    $set = GlobalSet::query()->where('handle', 'footer')->firstOrFail();
    app(SaveGlobals::class)->handle($set, ['legal' => $footer['values']['en']['legal']], 'en');

    $html = (string) $this->get('/')->getContent();
    expect(substr_count($html, '©') + substr_count($html, '&copy;'))->toBe(1)->and($html)->toContain('Licensed real estate broker');
});
