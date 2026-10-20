<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\DeleteBlueprint;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Collections\SaveCollection;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Admin\EntryList;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\actingAs;

beforeEach(fn () => actingAs(adminUser()));

it('creates a collection with a blueprint', function () {
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [['handle' => 'intro', 'type' => 'textarea']]]);
    $collection = app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);

    expect($collection->fresh()?->dated)->toBeTrue()
        ->and($collection->schema()->field('intro'))->not->toBeNull()
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('created');
});

it('changes a collection, but not its handle', function () {
    $collection = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    app(SaveCollection::class)->handle(['title' => 'Site pages'], $collection);

    expect($collection->fresh()?->title)->toBe('Site pages')
        ->and(fn () => app(SaveCollection::class)->handle(['handle' => 'other', 'title' => 'X'], $collection))->toThrow(ValidationException::class);
});

it('refuses an unknown blueprint and a handle a deleted collection had', function () {
    Collection::query()->create(['handle' => 'news', 'title' => 'News'])->delete();

    expect(fn () => app(SaveCollection::class)->handle(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'nope']))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveCollection::class)->handle(['handle' => 'news', 'title' => 'News']))->toThrow(ValidationException::class, 'perhaps a deleted one');
});

it('keeps entries when a collection is deleted', function () {
    $collection = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    $translation = app(CreateEntry::class)->handle($collection, ['title' => 'About']);
    app(DeleteCollection::class)->handle($collection);

    expect(Collection::query()->count())->toBe(0)
        ->and($translation->fresh()?->entry->collection->trashed())->toBeTrue()
        ->and(fn () => app(CreateEntry::class)->handle($collection, ['title' => 'Team']))->toThrow(ValidationException::class, 'was deleted');
});

it('refuses to delete a blueprint a collection uses', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);

    app(DeleteBlueprint::class)->handle($blueprint);
})->throws(ValidationException::class, 'used by the collection "Pages"');

it('switches entry pages and the listing page on and off, with default addresses', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $blog = app(SaveCollection::class)->handle(['handle' => 'blog', 'title' => 'Blog']);

    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update'));
    Pest\Laravel\get("/admin/cms/collections/{$blog->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('values.pages', false)->where('values.listing', false)->where('values.translatable', true));

    // On without an address: the defaults, from the handle.
    Pest\Laravel\put("/admin/cms/collections/{$blog->id}", ['title' => 'Blog', 'per_page' => 10, 'pages' => true, 'listing' => true])->assertSessionHasNoErrors();
    expect($blog->refresh()->route)->toBe('/blog/{slug}')->and($blog->listing_route)->toBe('/blog');

    // Given ones are kept; off clears them.
    Pest\Laravel\put("/admin/cms/collections/{$blog->id}", ['title' => 'Blog', 'per_page' => 10, 'pages' => true, 'route' => '/news/{slug}', 'listing' => false, 'listing_route' => '/news'])->assertSessionHasNoErrors();
    expect($blog->refresh()->route)->toBe('/news/{slug}')->and($blog->listing_route)->toBeNull();
});

it('keeps the entries of a collection that is not translatable in the main language', function () {
    $faq = app(SaveCollection::class)->handle(['handle' => 'faq', 'title' => 'FAQ', 'translatable' => false]);
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $entry = app(CreateEntry::class)->handle($faq, ['title' => 'Shipping'])->entry;

    expect(fn () => app(CreateTranslation::class)->handle($entry, 'id'))
        ->toThrow(ValidationException::class, "FAQ isn't translatable")
        ->and(array_column(EntryList::locales($entry->refresh()), 'code'))->toBe(['en']);
});
