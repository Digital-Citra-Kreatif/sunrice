<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Collections\PurgeDeleted;
use Sunrice\Cms\Actions\Collections\SaveCollection;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Storage::fake('public');
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/news/{slug}']);
    app(CreateEntry::class)->handle($this->news, ['title' => 'One']);
    app(TrashEntry::class)->handle(app(CreateEntry::class)->handle($this->news, ['title' => 'Two'])->entry);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    app(SaveTerm::class)->handle($this->tags, ['title' => 'Red']);
    app(TrashTerm::class)->handle(app(SaveTerm::class)->handle($this->tags, ['title' => 'Blue']));
    app(DeleteCollection::class)->handle($this->news);
    $this->tags->delete();
});

it('lists deleted collections and taxonomies, missing media files and links to missing entries', function () {
    Media::query()->create(['disk' => 'public', 'path' => 'gone/photo.jpg', 'filename' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    Storage::disk('public')->put('here/photo.jpg', 'x');
    Media::query()->create(['disk' => 'public', 'path' => 'here/photo.jpg', 'filename' => 'here.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['pages']]]]]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $about = app(CreateEntry::class)->handle($pages, ['title' => 'About'])->entry;
    app(CreateEntry::class)->handle($pages, ['title' => 'Home', 'data' => ['related' => $about->id]]);
    app(TrashEntry::class)->handle($about);
    app(ForceDeleteEntry::class)->handle($about->fresh(), force: true);

    artisan('sunrice:orphans')
        ->expectsTable(['Type', 'Handle', 'Title', 'Keeps', 'Deleted'], [
            ['Collection', 'news', 'News', '2 entries', $this->news->fresh()->deleted_at->toDateTimeString()],
            ['Taxonomy', 'tags', 'Tags', '2 terms', $this->tags->fresh()->deleted_at->toDateTimeString()],
        ])
        ->expectsOutputToContain('gone/photo.jpg')
        ->expectsOutputToContain('entry '.$about->id)
        ->doesntExpectOutputToContain('here/photo.jpg')
        ->assertSuccessful();
    expect(Collection::withTrashed()->where('handle', 'news')->exists())->toBeTrue();
});

it('purges deleted collections and taxonomies with what they kept, freeing their handles', function () {
    artisan('sunrice:orphans', ['--purge' => true])->expectsConfirmation('Delete the deleted collections and taxonomies listed, with their entries and terms, for good? This can\'t be undone.', 'no')->assertFailed();
    expect(Collection::withTrashed()->where('handle', 'news')->exists())->toBeTrue();

    artisan('sunrice:orphans', ['--purge' => true, '--force' => true])->expectsOutputToContain('handle is free')->assertSuccessful();
    expect(Collection::withTrashed()->where('handle', 'news')->exists())->toBeFalse()
        ->and(Entry::withTrashed()->count())->toBe(0)
        ->and(Taxonomy::withTrashed()->count())->toBe(0)
        ->and(Term::withTrashed()->count())->toBe(0);

    actingAs(adminUser());
    expect(app(SaveCollection::class)->handle(['handle' => 'news', 'title' => 'News again'])->exists)->toBeTrue();
});

it('refuses to purge what is not deleted', function () {
    $live = Collection::query()->create(['handle' => 'live', 'title' => 'Live']);

    expect(fn () => app(PurgeDeleted::class)->collection($live))->toThrow(InvalidArgumentException::class);
});

it('restores deleted collections and taxonomies from their lists', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete', 'cms.taxonomies.view', 'cms.taxonomies.delete'));

    get('/admin/collections?filters[trashed]=only')->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.id', $this->news->id)->where('rows.data.0.actions', ['restore']));
    post("/admin/collections/{$this->news->id}/actions/restore")->assertSessionHas('success', 'Restored.');
    expect($this->news->fresh()->trashed())->toBeFalse()
        ->and(Entry::query()->where('collection_id', $this->news->id)->count())->toBe(1);

    post("/admin/taxonomies/{$this->tags->id}/actions/restore")->assertSessionHas('success', 'Restored.');
    expect($this->tags->fresh()->trashed())->toBeFalse();
});

it('refuses to restore a collection whose address another one took', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.delete'));
    Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'route' => '/news/{slug}']);

    post("/admin/collections/{$this->news->id}/actions/restore")->assertSessionHas('error', fn (string $message) => str_contains($message, '/news/{slug}'));
    expect($this->news->fresh()->trashed())->toBeTrue();
});
