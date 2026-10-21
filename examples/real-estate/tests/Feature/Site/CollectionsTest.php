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

it('names the post\'s author agent as its author in the structured data, not the company', function (): void {
    $html = (string) $this->get('/journal/market-report-7')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $graph = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? []);
    $article = $graph->first(fn ($node) => in_array($node['@type'] ?? null, ['BlogPosting', 'Article'], true));

    expect($article['author'])->toMatchArray(['@type' => 'Person', 'name' => 'Ayu Pratiwi', 'url' => url('/team/ayu-pratiwi')]);
});

it('asks before stopping reply emails, in the site\'s layout', function (): void {
    $post = $this->site->entry('journal/market-report-7');
    $comment = Comment::query()->create(['entry_id' => $post->entry_id, 'author_name' => 'Dian', 'author_email' => 'dian@example.com',
        'body' => 'Thanks', 'status' => Comment::APPROVED, 'locale' => 'en', 'notify_replies' => true]);
    $url = \Illuminate\Support\Facades\URL::signedRoute('sunrice.cms.comments.unsubscribe', ['comment' => $comment->id]);

    $this->get($url)->assertOk()
        ->assertSee('Stop these emails')
        ->assertSee('class="btn btn-primary"', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<footer', false); // the site's own layout, not the CMS's plain card
    expect($comment->refresh()->notify_replies)->toBeTrue();
});

it('searches the site', function (): void {
    $this->get('/search?q=Samudra')->assertOk()->assertSee('Villa Samudra')->assertSee('noindex', false);
    $this->get('/id/search?q=Samudra')->assertOk()->assertSee('Vila Samudra');
});

it('describes an agent\'s languages as schema.org Languages', function (): void {
    $html = (string) $this->get('/team/ayu-pratiwi')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $person = collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->firstWhere('@type', 'Person');

    expect($person['knowsLanguage'])->toBe([
        ['@type' => 'Language', 'name' => 'English', 'alternateName' => 'en'],
        ['@type' => 'Language', 'name' => 'Indonesian', 'alternateName' => 'id'],
    ]);
});
