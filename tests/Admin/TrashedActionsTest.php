<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Note;
use Sunrice\Tests\Admin\Fixtures\NoteResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Note::createTable();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('desk', register: fn (Panel $p) => $p->resources(NoteResource::class))));
    actingAs(adminUser(Panel::ACCESS, 'desk.notes.view', 'desk.notes.delete'));
});

it('lists records in the trash with a filter and offers a trashed action only for them', function () {
    Note::query()->create(['title' => 'Kept']);
    $gone = Note::query()->create(['title' => 'Gone']);
    $gone->delete();

    get('/admin/desk/notes')->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.actions', []));
    get('/admin/desk/notes?filters[trashed]=only')->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.id', $gone->id)
        ->where('rows.data.0.actions', ['restore']));
});

it('runs a trashed action on a record in the trash, and refuses others', function () {
    $kept = Note::query()->create(['title' => 'Kept']);
    $gone = Note::query()->create(['title' => 'Gone']);
    $gone->delete();

    post("/admin/desk/notes/{$gone->id}/actions/restore")->assertSessionHas('success', 'Restored.');
    expect($gone->fresh()?->trashed())->toBeFalse();
    post("/admin/desk/notes/{$kept->id}/actions/restore")->assertForbidden();
});

it('has a trash of its own for models with SoftDeletes: ?status=trashed, Restore one or many', function () {
    $kept = Note::query()->create(['title' => 'Kept']);
    $gone = Note::query()->create(['title' => 'Gone']);
    $also = Note::query()->create(['title' => 'Also gone']);
    $gone->delete();
    $also->delete();

    get('/admin/desk/notes')->assertInertia(fn (Assert $page) => $page->where('can.trash', true)->where('query.status', '')->has('rows.data', 1)
        ->where('bulkActions.0', ['key' => 'delete', 'label' => 'Move to trash', 'confirm' => 'Move the selected records to the trash? You can restore them.', 'destructive' => true, 'fields' => []]));
    get('/admin/desk/notes?status=trashed')->assertInertia(fn (Assert $page) => $page->where('query.status', 'trashed')->has('rows.data', 2));

    post("/admin/desk/notes/{$gone->id}/restore")->assertSessionHas('success', 'Gone restored.');
    post("/admin/desk/notes/{$kept->id}/restore")->assertNotFound(); // not in the trash
    post('/admin/desk/notes/bulk', ['action' => 'restore', 'ids' => [$also->id]])->assertSessionHas('success', '1 restored.');
    expect(Note::query()->count())->toBe(3);

    actingAs(adminUser(Panel::ACCESS, 'desk.notes.view'));
    get('/admin/desk/notes')->assertInertia(fn (Assert $page) => $page->where('can.trash', false));
    $also->delete();
    post("/admin/desk/notes/{$also->id}/restore")->assertForbidden();
});
