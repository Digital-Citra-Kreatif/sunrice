<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Sunrice\Cms\Models\Submission;
use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

it('keeps a contact message and thanks the visitor', function (): void {
    $this->from('/contact')->post(route('sunrice.cms.forms.submit', 'contact'), [
        'data' => ['name' => 'Rina', 'email' => 'rina@example.com', 'topic' => 'buying', 'message' => 'A villa in Ubud, please.'],
        'locale' => 'en', '_form' => 'contact', ...honeypot(),
    ])->assertRedirect('/contact');

    expect(Submission::query()->latest('id')->first()?->data)->toMatchArray(['name' => 'Rina', 'topic' => 'buying']);
    $this->get('/contact')->assertSee('role="status"', false);
});

it('sends the contact form back with errors in its own markup', function (): void {
    $this->from('/contact')->post(route('sunrice.cms.forms.submit', 'contact'), [
        'data' => ['name' => '', 'email' => 'not-an-email', 'message' => ''],
        'locale' => 'en', '_form' => 'contact', ...honeypot(),
    ])->assertRedirect('/contact');

    $this->get('/contact')->assertSee('aria-invalid="true"', false)->assertSee('Please check the fields marked below.');
});

it('keeps an inquiry with the property it was sent from', function (): void {
    $this->from('/properties/villa-samudra')->post(route('sunrice.cms.forms.submit', 'inquiry'), [
        'data' => ['name' => 'Tom', 'email' => 'tom@example.com', 'property' => 'Villa Samudra', 'message' => 'Viewing on Saturday?'],
        'locale' => 'en', '_form' => 'inquiry', ...honeypot(),
    ])->assertRedirect();

    expect(Submission::query()->latest('id')->first()?->data['property'])->toBe('Villa Samudra');
});

it('takes a valuation request with photos', function (): void {
    $this->from('/sell-with-us')->post(route('sunrice.cms.forms.submit', 'valuation'), [
        'data' => ['name' => 'Wayan', 'email' => 'wayan@example.com', 'address' => 'Jl. Raya Ubud 1', 'property_type' => 'villa', 'bedrooms' => 3,
            'photos' => [UploadedFile::fake()->image('villa.jpg', 800, 600), UploadedFile::fake()->image('garden.jpg', 800, 600)]],
        'locale' => 'en', '_form' => 'valuation', ...honeypot(),
    ])->assertRedirect();

    expect(array_column(Submission::query()->latest('id')->first()?->data['photos'] ?? [], 'name'))->toBe(['villa.jpg', 'garden.jpg']);
});

it('signs visitors up for the newsletter from the footer', function (): void {
    $this->from('/')->postJson(route('sunrice.cms.forms.submit', 'newsletter'), [
        'data' => ['email' => 'reader@example.com'], 'locale' => 'en', '_form' => 'newsletter', ...honeypot(),
    ])->assertCreated();
});
