<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Fields\Block;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Items;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Fieldset;

/** Validation errors for data against fields, by path. */
function fieldErrors(Schema $schema, array $data): array
{
    return Validator::make(['data' => $data], $schema->rules($data))->errors()->toArray();
}

function makeMedia(string $mime = 'image/jpeg'): Media
{
    return Media::query()->create(['disk' => 'public', 'path' => 'a/'.uniqid().'.jpg', 'filename' => 'photo.jpg', 'mime_type' => $mime, 'size' => 10]);
}

function hydration(): HydrationContext
{
    return new HydrationContext('en');
}

it('validates required fields first', function () {
    $schema = Schema::make([new Field('title', 'text', required: true), Field::make('count', 'number', ['min' => 1, 'step' => 1])]);

    expect(fieldErrors($schema, ['title' => '', 'count' => 1.5]))->toHaveKeys(['data.title', 'data.count'])
        ->and(fieldErrors($schema, ['title' => 'Hi', 'count' => 0]))->toHaveKey('data.count')
        ->and(fieldErrors($schema, ['title' => 'Hi', 'count' => 3]))->toBe([]);
});

it('limits text only to the field\'s own maximum, with a ceiling', function () {
    $schema = Schema::make([Field::make('title', 'text'), Field::make('short', 'text', ['max' => 10])]);

    expect(fieldErrors($schema, ['title' => str_repeat('a', 1000), 'short' => str_repeat('a', 10)]))->toBe([])
        ->and(fieldErrors($schema, ['title' => str_repeat('a', 65536), 'short' => str_repeat('a', 11)]))->toHaveKeys(['data.title', 'data.short']);
});

it('adds the field\'s extra rules', function () {
    $schema = Schema::make([new Field('code', 'text', validation: ['alpha_num', 'size:4'])]);

    expect(fieldErrors($schema, ['code' => 'ab-1']))->toHaveKey('data.code')
        ->and(fieldErrors($schema, ['code' => 'ab12']))->toBe([]);
});

it('keeps data under handles the fields don\'t have', function () {
    $schema = Schema::make([Field::make('title', 'text')]);

    expect($schema->normalize(['title' => '  Hello ', 'old' => 'kept']))->toBe(['title' => 'Hello', 'old' => 'kept']);
});

it('gives defaults for new content', function () {
    $schema = Schema::make([
        Field::make('title', 'text', ['default' => 'Untitled']),
        Field::make('featured', 'toggle'),
        Field::make('tags', 'select', ['multiple' => true, 'options' => [['value' => 'a']]]),
        Field::make('rows', 'repeater'),
    ]);

    expect($schema->defaults())->toBe(['title' => 'Untitled', 'featured' => false, 'tags' => [], 'rows' => []]);
});

it('allows only listed options', function () {
    $one = Schema::make([Field::make('size', 'select', ['options' => [['value' => 's', 'label' => 'Small'], ['value' => 'm']]])]);
    $many = Schema::make([Field::make('sizes', 'select', ['multiple' => true, 'options' => ['s', 'm']])]);

    expect(fieldErrors($one, ['size' => 'xl']))->toHaveKey('data.size')
        ->and(fieldErrors($one, ['size' => 'm']))->toBe([])
        ->and(fieldErrors($many, ['sizes' => ['s', 'xl']]))->toHaveKey('data.sizes.1')
        ->and($many->normalize(['sizes' => ['s', 'm']]))->toBe(['sizes' => ['s', 'm']]);
});

it('stores dates and hands templates a Carbon', function () {
    $schema = Schema::make([Field::make('day', 'date'), Field::make('at', 'date', ['time' => true])]);
    $data = $schema->normalize(['day' => '2026-10-19 14:30', 'at' => '2026-10-19 14:30']);

    expect($data)->toBe(['day' => '2026-10-19', 'at' => '2026-10-19T14:30:00'])
        ->and($schema->hydrate($data, hydration())['at'])->toBeInstanceOf(Carbon::class);
});

it('cleans rich text and tracks its images', function () {
    $image = makeMedia();
    $schema = Schema::make([Field::make('body', 'rich_text')]);
    $data = $schema->normalize(['body' => '<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">link</a></p><img src="/a.jpg" data-media-id="'.$image->id.'">']);

    expect($data['body'])->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript')
        ->toContain('data-media-id="'.$image->id.'"')
        ->and($schema->references($data))->toEqual([new Reference('media', $image->id, 'body')])
        ->and($schema->normalize(['body' => '<p> </p>']))->toBe(['body' => null]);
});

it('refuses links that would run script', function (string $url, bool $ok) {
    $schema = Schema::make([Field::make('cta', 'link')]);

    expect(fieldErrors($schema, ['cta' => ['url' => $url]]) === [])->toBe($ok);
})->with([
    ['https://example.com', true],
    ['/about', true],
    ['#top', true],
    ['mailto:hi@example.com', true],
    ['javascript:alert(1)', false],
    [" java\tscript:alert(1)", false],
    ['data:text/html,<script>', false],
    ['/\\evil.com', false],
]);

it('never prints an unsafe link saved before', function () {
    $field = Field::make('cta', 'link');

    expect(Schema::make([$field])->hydrate(['cta' => ['url' => 'javascript:alert(1)']], hydration()))->toBe(['cta' => null])
        ->and(Schema::make([$field])->hydrate(['cta' => ['url' => '/a', 'label' => 'A', 'new_tab' => true]], hydration()))
        ->toBe(['cta' => ['type' => 'url', 'url' => '/a', 'label' => 'A', 'title' => null, 'new_tab' => true]]);
});

it('accepts only existing media of the allowed kinds', function () {
    $image = makeMedia();
    $pdf = makeMedia('application/pdf');
    $trashed = makeMedia();
    $trashed->delete();
    $one = Schema::make([Field::make('cover', 'media', ['types' => ['image']])]);
    $many = Schema::make([Field::make('gallery', 'media', ['multiple' => true, 'max' => 2])]);

    expect(fieldErrors($one, ['cover' => $pdf->id]))->toHaveKey('data.cover')
        ->and(fieldErrors($one, ['cover' => $trashed->id]))->toHaveKey('data.cover')
        ->and(fieldErrors($one, ['cover' => $image->id]))->toBe([])
        ->and(fieldErrors($many, ['gallery' => [$image->id, $pdf->id]]))->toBe([])
        ->and(fieldErrors($many, ['gallery' => [$image->id, $pdf->id, $image->id]]))->toHaveKeys(['data.gallery', 'data.gallery.0']);
});

it('hydrates media in one query, in order, skipping missing files', function () {
    $a = makeMedia();
    $b = makeMedia();
    $schema = Schema::make([Field::make('gallery', 'media', ['multiple' => true]), Field::make('cover', 'media')]);
    $data = $schema->normalize(['gallery' => [(string) $b->id, $a->id, 999], 'cover' => $a->id]);

    DB::enableQueryLog();
    $hydrated = $schema->hydrate($data, hydration());

    expect($data['gallery'])->toBe([$b->id, $a->id, 999])
        ->and($hydrated['gallery']->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and($hydrated['cover']->id)->toBe($a->id)
        ->and(DB::getQueryLog())->toHaveCount(1)
        ->and($schema->references($data))->toEqual([
            new Reference('media', $b->id, 'gallery'), new Reference('media', $a->id, 'gallery'),
            new Reference('media', 999, 'gallery'), new Reference('media', $a->id, 'cover'),
        ]);
});

it('validates and stores groups', function () {
    $schema = Schema::make([new Field('hero', 'group', fields: [new Field('heading', 'text', required: true), Field::make('image', 'media')])]);

    expect(fieldErrors($schema, ['hero' => ['heading' => '']]))->toHaveKey('data.hero.heading')
        ->and($schema->normalize(['hero' => ['heading' => ' Hi ', 'image' => null]]))->toBe(['hero' => ['heading' => 'Hi', 'image' => null]])
        ->and($schema->field('hero.heading')?->required)->toBeTrue()
        ->and($schema->toAdmin()[0]['fields'][0]['handle'])->toBe('heading');
});

it('gives repeater rows a stable id, an optional key and a Show switch', function () {
    $schema = Schema::make([new Field('features', 'repeater', config: ['max' => 3], fields: [new Field('title', 'text', required: true)])]);

    expect(fieldErrors($schema, ['features' => [['title' => 'A'], ['title' => '']]]))->toHaveKey('data.features.1.title')
        ->and(fieldErrors($schema, ['features' => array_fill(0, 4, ['title' => 'A'])]))->toHaveKey('data.features');

    $rows = $schema->normalize(['features' => [['title' => 'A', '_key' => ' pricing '], ['title' => 'B', '_hidden' => true, '_id' => 'keep']]])['features'];
    expect($rows[0]['_id'])->toBeString()->not->toBe('')
        ->and($rows[0]['_key'])->toBe('pricing')
        ->and($rows[1])->toBe(['title' => 'B', '_hidden' => true, '_id' => 'keep', '_key' => null]);

    $items = $schema->hydrate(['features' => $rows], hydration())['features'];
    expect($items)->toBeInstanceOf(Items::class)->toHaveCount(1)
        ->and($items->byKey('pricing')['title'])->toBe('A');
});

it('builds flexible blocks from fieldsets', function () {
    Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => [['handle' => 'text', 'type' => 'text', 'required' => true], ['handle' => 'author', 'type' => 'text']]]);
    Fieldset::query()->create(['handle' => 'image', 'title' => 'Image', 'fields' => [['handle' => 'file', 'type' => 'media']]]);
    $schema = Schema::make([Field::make('sections', 'flexible', ['blocks' => ['quote']])]);

    expect(fieldErrors($schema, ['sections' => [['type' => 'image', 'values' => []]]]))->toHaveKey('data.sections.0.type')
        ->and(fieldErrors($schema, ['sections' => [['type' => 'quote', 'values' => ['text' => '']]]]))->toHaveKey('data.sections.0.values.text');

    $stored = $schema->normalize(['sections' => [
        ['type' => 'quote', 'key' => 'intro', 'values' => ['text' => ' To be ', 'author' => 'W']],
        ['type' => 'quote', 'hidden' => true, 'values' => ['text' => 'Hidden']],
    ]])['sections'];
    expect($stored[0])->toMatchArray(['type' => 'quote', 'key' => 'intro', 'hidden' => false, 'values' => ['text' => 'To be', 'author' => 'W']]);

    $blocks = $schema->hydrate(['sections' => $stored], hydration())['sections'];
    expect($blocks)->toHaveCount(1)
        ->and($blocks->byKey('intro'))->toBeInstanceOf(Block::class)
        ->and($blocks->byKey('intro')->text)->toBe('To be')
        ->and($blocks->first()['author'])->toBe('W')
        ->and($schema->toAdmin()[0]['blocks'][0])->toMatchArray(['type' => 'quote', 'label' => 'Quote']);
});

it('includes fieldsets in place, keeping an include whose fieldset is gone', function () {
    Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text'], ['handle' => 'meta_description', 'type' => 'textarea']]]);
    $schema = Schema::make([
        Field::make('title', 'text'),
        Field::make('seo_fields', 'fieldset', ['fieldset' => 'seo']),
        Field::make('gone', 'fieldset', ['fieldset' => 'missing']),
    ]);

    expect(array_map(fn (Field $f) => $f->handle, $schema->fields()))->toBe(['title', 'meta_title', 'meta_description', 'gone'])
        ->and($schema->normalize(['gone' => ['x' => 1]]))->toBe(['gone' => ['x' => 1]]);
});

it('stops expanding fieldsets that include each other', function () {
    Fieldset::query()->create(['handle' => 'a', 'title' => 'A', 'fields' => [['handle' => 'from_a', 'type' => 'text'], ['handle' => 'b', 'type' => 'fieldset', 'config' => ['fieldset' => 'b']]]]);
    Fieldset::query()->create(['handle' => 'b', 'title' => 'B', 'fields' => [['handle' => 'from_b', 'type' => 'text'], ['handle' => 'a', 'type' => 'fieldset', 'config' => ['fieldset' => 'a']]]]);

    $handles = array_map(fn (Field $f) => $f->handle, Schema::make([Field::make('a', 'fieldset', ['fieldset' => 'a'])])->fields());

    expect($handles)->toBe(['from_a', 'from_b', 'a']);
});

it('sends the editor blocks that use their own fieldset only so deep', function () {
    // A section may hold sections; a card may hold sections too.
    Fieldset::query()->create(['handle' => 'section', 'title' => 'Section', 'fields' => [
        ['handle' => 'heading', 'type' => 'text'],
        ['handle' => 'columns', 'type' => 'group', 'fields' => [['handle' => 'content', 'type' => 'flexible', 'config' => ['blocks' => ['section', 'card']]]]],
    ]]);
    Fieldset::query()->create(['handle' => 'card', 'title' => 'Card', 'fields' => [['handle' => 'inner', 'type' => 'flexible', 'config' => ['blocks' => ['section']]]]]);
    $schema = Schema::make([Field::make('sections', 'flexible', ['blocks' => ['section']])]);

    $admin = $schema->toAdmin();
    $depth = 0;
    for ($block = $admin[0]['blocks'][0]; $block['fields'] !== []; $block = $block['fields'][1]['fields'][0]['blocks'][0]) {
        expect($block)->toMatchArray(['type' => 'section', 'label' => 'Section'])
            ->and($block['fields'][0]['handle'])->toBe('heading');
        $depth++;
    }
    expect($depth)->toBe(Schema::MAX_DEPTH)
        ->and($block)->toBe(['type' => 'section', 'label' => 'Section', 'fields' => [], 'defaults' => []])
        // Content still validates and stores as deep as it goes.
        ->and(fieldErrors($schema, ['sections' => [['type' => 'section', 'values' => ['columns' => ['content' => [['type' => 'card', 'values' => ['inner' => [['type' => 'nope']]]]]]]]]]))
        ->toHaveKey('data.sections.0.values.columns.content.0.values.inner.0.type');
});

it('says which fields are translatable', function () {
    $schema = Schema::make([Field::make('title', 'text'), Field::make('price', 'number'), new Field('sku', 'text', translatable: false)]);

    expect(array_map(fn (array $f) => $f['translatable'], $schema->toAdmin()))->toBe([true, false, false]);
});

it('keeps what the rich text editor writes', function () {
    $html = '<h2>A heading</h2><p>Plain <strong>bold</strong> <em>it</em> <u>under</u> <s>gone</s> <code>x</code> '
        .'<a target="_blank" rel="noopener noreferrer nofollow" href="/about">link</a></p>'
        .'<ul><li><p>one</p></li></ul><ol><li><p>two</p></li></ol><blockquote><p>quote</p></blockquote>'
        .'<pre><code>code()</code></pre><hr><img src="https://example.com/a.png" alt="A" title="T" data-media-id="7">';
    $stored = Schema::make([Field::make('body', 'rich_text')])->normalize(['body' => $html])['body'];

    foreach (['<h2>A heading</h2>', '<strong>bold</strong>', '<em>it</em>', '<u>under</u>', '<s>gone</s>', '<code>x</code>',
        'href="/about"', 'target="_blank"', '<ul><li><p>one</p></li></ul>', '<ol><li><p>two</p></li></ol>',
        '<blockquote><p>quote</p></blockquote>', '<pre><code>code()</code></pre>', '<hr', 'data-media-id="7"', 'alt="A"'] as $kept) {
        expect($stored)->toContain($kept);
    }
});

it('never stores rel="nofollow" on rich text links, the site\'s own included', function () {
    $stored = Schema::make([Field::make('body', 'rich_text')])
        ->normalize(['body' => '<p><a rel="noopener noreferrer nofollow" href="/sell-with-us">Sell with us</a></p>'])['body'];

    expect($stored)->toContain('href="/sell-with-us"')->not->toContain('nofollow');
});
