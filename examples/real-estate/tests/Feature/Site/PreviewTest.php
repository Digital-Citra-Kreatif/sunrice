<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
    $property = $this->site->entry('properties/villa-samudra');
    app(SaveDraft::class)->handle($property, ['title' => 'Villa Samudra (new photos)', 'version' => $property->version]);
});

it('previews a draft in the property template, never indexed or cached', function (): void {
    $translation = $this->site->entry('properties/villa-samudra');
    $url = URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(30), ['translation' => $translation->id]);

    $this->get($url)->assertOk()
        ->assertSee('Villa Samudra (new photos)')
        ->assertSee('<meta name="robots" content="noindex', false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertDontSee('id="comment-form"', false);
});

it('shows signed-in editors the draft banner and the draft itself with ?draft', function (): void {
    Artisan::call('sunrice:sync-permissions');
    $editor = User::factory()->create();
    $editor->assignRole(config('sunrice-admin.super_admin_role'));

    $this->actingAs($editor)->get('/properties/villa-samudra')->assertOk()
        ->assertSee('data-sunrice-draft-banner', false)
        ->assertSee('Villa Samudra</h1>', false);
    $html = $this->actingAs($editor)->get('/properties/villa-samudra?draft')->assertOk()
        ->assertSee('Villa Samudra (new photos)')->getContent();
    expect($html)->toMatch('/<body class="[^"]*\bis-draft\b/');

    // Visitors see the live page only.
    auth()->logout();
    $this->get('/properties/villa-samudra?draft')->assertDontSee('Villa Samudra (new photos)');
});
