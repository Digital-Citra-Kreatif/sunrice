<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\Access;
use Sunrice\Cms\Support\Handles;
use Sunrice\Cms\Support\ScopedPermissions;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

/*
 * What every admin page costs: the sidebar asks whether the user may see
 * some collection's entries, terms and submissions, and the panel whether
 * the account is locked or signed out. None of that should hit the
 * database on every page.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Collection::query()->create(['handle' => 'blog', 'title' => 'Blog']);
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact']);
});

/** How many database queries `$run` takes. */
function adminQueries(Closure $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('keeps to a query budget per admin page', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.terms.tags.manage', 'cms.submissions.contact.view'));
    get('/admin')->assertOk(); // warms the caches

    expect(adminQueries(fn () => get('/admin')->assertOk()))->toBeLessThanOrEqual(4)
        ->and(adminQueries(fn () => Handles::collections()))->toBe(0)
        ->and(adminQueries(fn () => ScopedPermissions::all()))->toBe(0)
        ->and(adminQueries(fn () => app(AccountGuard::class)->isLocked(auth()->user())))->toBe(0);
});

it('forgets the cached handles and permissions when a collection, taxonomy or form changes', function () {
    expect(Handles::collections())->toBe(['blog'])->and(Handles::taxonomies())->toBe(['tags'])->and(Handles::forms())->toBe(['contact']); // cached now

    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News']);
    Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    Form::query()->create(['handle' => 'apply', 'title' => 'Apply']);
    $user = adminUser(Panel::ACCESS, 'cms.entries.news.view', 'cms.terms.topics.manage', 'cms.submissions.apply.view');
    expect(Access::entries($user, 'view'))->toBeTrue()->and(Access::terms($user))->toBeTrue()->and(Access::submissions($user, 'view'))->toBeTrue()
        ->and(collect(ScopedPermissions::all())->firstWhere('name', 'cms.entries.news.view')?->group)->toBe('Entries: News');

    $news->update(['title' => 'Headlines']);
    expect(collect(ScopedPermissions::all())->firstWhere('name', 'cms.entries.news.view')?->group)->toBe('Entries: Headlines');

    $news->delete();
    expect(Access::entries($user, 'view'))->toBeFalse();
    $news->restore();
    expect(Access::entries($user, 'view'))->toBeTrue();
});

it('forgets the cached account states on lock, unlock and sign out everywhere', function () {
    $rina = adminUser(Panel::ACCESS);
    $guard = app(AccountGuard::class);
    expect($guard->isLocked($rina))->toBeFalse();

    $guard->lock($rina, 'checking');
    expect(app(AccountGuard::class)->isLocked($rina))->toBeTrue()
        ->and(app(AccountGuard::class)->sessionAllowed(request(), $rina))->toBeFalse();

    $guard->unlock($rina);
    expect(app(AccountGuard::class)->isLocked($rina))->toBeFalse()
        ->and(app(AccountGuard::class)->sessionAllowed(request(), $rina))->toBeTrue();

    // Signed in after the lock's sign-out: fine, until signed out everywhere again.
    actingAs($rina)->withSession([AccountGuard::LOGIN_AT => now()->getTimestamp()]);
    get('/admin')->assertOk();
    travelTo(now()->addMinute());
    actingAs(adminUser(Panel::ACCESS));
    app(AccountGuard::class)->signOutEverywhere($rina);
    actingAs($rina)->withSession([AccountGuard::LOGIN_AT => now()->subMinute()->getTimestamp()]);
    get('/admin')->assertRedirect('/admin/login');
});
