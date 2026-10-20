<?php

declare(strict_types=1);

use Sunrice\Cms\Models\Comment;
use Tests\Support\LuminaSite;

beforeEach(function (): void {
    $this->site = LuminaSite::build();
});

it('lists the team in manual order and shows an agent with their properties', function (): void {
    $this->get('/team')->assertOk()->assertSeeInOrder(['Ayu Pratiwi', 'Budi Santoso']);
    $this->get('/team/ayu-pratiwi')->assertOk()
        ->assertSee('Founder &amp; Principal', false)
        ->assertSee('Properties with Ayu')
        ->assertSeeInOrder(['Villa Samudra', 'Kemang Garden House'])   // orderBy price desc
        ->assertDontSee('Seminyak Pool Villa')
        ->assertSee('"@type":"Person"', false);
});

it('renders neighbourhood guides with their properties', function (): void {
    $this->get('/neighbourhoods')->assertOk()->assertSee('Canggu');
    $this->get('/neighbourhoods/canggu')->assertOk()
        ->assertSee('Rice fields, surf and slow mornings.')
        ->assertSee('IDR 18.5 billion')
        ->assertSee('Properties in Canggu')->assertSee('Villa Samudra');
});

it('paginates the journal six a page and lists its topics', function (): void {
    $this->get('/journal')->assertOk()->assertSee('Market report 7')->assertSee('Market report 2')->assertDontSee('Market report 1<')
        ->assertSee('Market reports');
    $this->get('/journal?page=2')->assertOk()->assertSee('Market report 1')
        ->assertSee('<link rel="canonical" href="'.url('/journal?page=2').'">', false);
    $this->get('/journal/topic/market-reports')->assertOk()->assertSee('Market report 7');
});

it('renders a post with its author, related properties and comments', function (): void {
    $this->get('/journal/market-report-7')->assertOk()
        ->assertSee('Ayu Pratiwi')
        ->assertSee('Properties in this story')->assertSee('Villa Samudra')
        ->assertSee('id="comment-form"', false)
        ->assertSee('More from the journal');
});

it('takes comments on journal posts', function (): void {
    $post = $this->site->entry('journal/market-report-7');
    $this->from('/journal/market-report-7')->post(route('sunrice.cms.comments.store', $post->entry_id), [
        'body' => 'Very useful, thank you.', 'author_name' => 'Dian', 'author_email' => 'dian@example.com', 'locale' => 'en',
        ...honeypot(),
    ])->assertRedirect();

    expect(Comment::query()->where('body', 'Very useful, thank you.')->exists())->toBeTrue();
});

it('searches the site', function (): void {
    $this->get('/search?q=Samudra')->assertOk()->assertSee('Villa Samudra')->assertSee('noindex', false);
    $this->get('/id/search?q=Samudra')->assertOk()->assertSee('Vila Samudra');
});
