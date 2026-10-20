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
