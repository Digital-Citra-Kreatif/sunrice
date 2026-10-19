<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Locks\EditLocks;
use Sunrice\Admin\Locks\KeptEdit;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p
        ->lockable('shop.product', fn (string $id, string $locale, Authorizable $user) => $id !== '404' && $user->can('shop.products.update')))));
    $this->ana = adminUser(Panel::ACCESS, 'shop.products.update');
    $this->ana->forceFill(['name' => 'Ana'])->save();
    $this->budi = adminUser(Panel::ACCESS, 'shop.products.update');
    $this->budi->forceFill(['name' => 'Budi'])->save();
});

function lockAs($user, string $editor, string $path = '', array $body = []): TestResponse
{
    actingAs($user);

    return postJson('/admin/locks/shop.product/7'.$path, ['editor' => $editor, 'locale' => 'en', ...$body]);
}

it('gives the lock to the first editor and tells the next who has it', function () {
    lockAs($this->ana, 'tab-a')->assertOk()->assertJson(['status' => 'ok', 'kept' => []]);
    lockAs($this->ana, 'tab-a2')->assertJson(['status' => 'ok']); // another tab of hers
    lockAs($this->budi, 'tab-b')->assertJson(['status' => 'locked', 'by' => 'Ana']);
    // Per language.
    actingAs($this->budi);
    postJson('/admin/locks/shop.product/7', ['editor' => 'tab-b', 'locale' => 'id'])->assertJson(['status' => 'ok']);

    lockAs($this->ana, 'tab-a2', '/release');
    lockAs($this->budi, 'tab-b')->assertJson(['status' => 'ok']);
});

it('expires a lock nobody renews', function () {
    lockAs($this->ana, 'tab-a');
    travel(EditLocks::TTL + 1)->seconds();
    lockAs($this->budi, 'tab-b')->assertJson(['status' => 'ok']);
});

it('takes over, tells the previous editor once, and keeps their changes', function () {
    lockAs($this->ana, 'tab-a');
    lockAs($this->budi, 'tab-b', '/take')->assertJson(['status' => 'ok']);

    lockAs($this->ana, 'tab-a')->assertJson(['status' => 'taken', 'by' => 'Budi']);
    lockAs($this->ana, 'tab-a', '/keep', ['content' => ['title' => 'Mine'], 'taken_by' => 'Budi'])->assertOk();
    lockAs($this->ana, 'tab-a')->assertJson(['status' => 'locked', 'by' => 'Budi']);

    $kept = lockAs($this->budi, 'tab-b')->json('kept');
    expect($kept)->toHaveCount(1)->and($kept[0])->toMatchArray(['by' => 'Ana', 'taken_by' => 'Budi']);
    actingAs($this->budi);
    getJson("/admin/kept-edits/{$kept[0]['id']}")->assertJson(['content' => ['title' => 'Mine']]);
    deleteJson("/admin/kept-edits/{$kept[0]['id']}")->assertOk();
    expect(KeptEdit::query()->count())->toBe(0);
});

it('checks the type and who may edit', function () {
    $viewer = adminUser(Panel::ACCESS);
    lockAs($viewer, 'tab-v')->assertForbidden();
    actingAs($this->ana);
    postJson('/admin/locks/shop.unknown/7', ['editor' => 'tab-a'])->assertNotFound();
    postJson('/admin/locks/shop.product/404', ['editor' => 'tab-a'])->assertForbidden();
    postJson('/admin/locks/shop.product/7', ['editor' => 'bad editor!'])->assertUnprocessable();

    $kept = KeptEdit::query()->create(['type' => 'shop.product', 'item_id' => '7', 'locale' => 'en', 'content' => ['a' => 1]]);
    actingAs($viewer);
    getJson("/admin/kept-edits/{$kept->id}")->assertForbidden();
});

it('prunes kept changes after a month', function () {
    KeptEdit::query()->create(['type' => 'shop.product', 'item_id' => '7', 'content' => ['a' => 1]]);
    travel(KeptEdit::KEEP_DAYS + 1)->days();
    KeptEdit::query()->create(['type' => 'shop.product', 'item_id' => '8', 'content' => ['a' => 1]]);

    artisan('model:prune', ['--model' => [KeptEdit::class]])->assertSuccessful();
    expect(KeptEdit::query()->pluck('item_id')->all())->toBe(['8']);
});

it('makes plugins prefix their lockable types', function () {
    $panel = Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->lockable('cms.entry', fn () => true)));
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'start with its id ("shop.…")');
});
