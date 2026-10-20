<?php

declare(strict_types=1);

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
    $this->get('/no-such-page')->assertNotFound()->assertSee('This page has moved on');
    $this->get('/id/tidak-ada')->assertNotFound()->assertSee('Halaman ini sudah pindah');
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
