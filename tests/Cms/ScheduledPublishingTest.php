<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Events\EntryPublished;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/*
 * sunrice:publish-scheduled: entries published for a later date are
 * announced once when it comes, whatever the app's timezone, a run that
 * failed or two runs at once.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/news/{slug}']);
    $this->timezone = date_default_timezone_get();
});

afterEach(function () {
    date_default_timezone_set($this->timezone);
});

function scheduledNews(string $title, DateTimeInterface $at): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->news, ['title' => $title]), $at);
}

/** @return list<string> the titles announced as gone live */
function announced(): array
{
    $titles = [];
    foreach (Event::dispatched(EntryPublished::class, fn (EntryPublished $event): bool => $event->scheduled) as [$event]) {
        $titles[] = (string) $event->translation->title;
    }

    return $titles;
}

it('announces each scheduled entry once, in an app timezone ahead of UTC or behind it', function (string $timezone) {
    config(['app.timezone' => $timezone]);
    date_default_timezone_set($timezone);
    $this->travelTo(now()->setTime(9, 0));
    artisan('sunrice:publish-scheduled')->assertSuccessful();
    scheduledNews('Later', now()->addMinutes(30));
    Event::fake([EntryPublished::class]);

    $this->travelTo(now()->addMinutes(29));
    artisan('sunrice:publish-scheduled')->expectsOutput('0 scheduled entries went live.');
    $this->travelTo(now()->addMinutes(2));
    artisan('sunrice:publish-scheduled')->expectsOutput('1 scheduled entry went live.');
    $this->travelTo(now()->addMinute());
    artisan('sunrice:publish-scheduled')->expectsOutput('0 scheduled entries went live.');

    expect(announced())->toBe(['Later']);
})->with(['Asia/Jakarta', 'America/New_York']);

it('announces again what a run that failed did not get to', function () {
    $this->travelTo(now()->setTime(9, 0));
    artisan('sunrice:publish-scheduled')->assertSuccessful();
    scheduledNews('Later', now()->addMinutes(30));
    // A listener of a plugin fails once (a mail server down…).
    $failed = false;
    Event::listen(EntryPublished::class, function (EntryPublished $event) use (&$failed): void {
        if ($event->scheduled && ! $failed) {
            $failed = true;
            throw new RuntimeException('Down');
        }
    });

    $this->travelTo(now()->addMinutes(31));
    expect(fn () => artisan('sunrice:publish-scheduled')->run())->toThrow(RuntimeException::class);
    $this->travelTo(now()->addMinute());
    artisan('sunrice:publish-scheduled')->expectsOutput('1 scheduled entry went live.');
});

it('leaves the work to a run already going on (another server\'s)', function () {
    $this->travelTo(now()->setTime(9, 0));
    artisan('sunrice:publish-scheduled')->assertSuccessful();
    scheduledNews('Later', now()->addMinutes(30));
    Event::fake([EntryPublished::class]);
    $this->travelTo(now()->addMinutes(31));

    // Another run holds the lock: this one does nothing, and the window stays for the next.
    $lock = Cache::lock('sunrice-cms:publish-scheduled', 60);
    expect($lock->get())->toBeTrue();
    artisan('sunrice:publish-scheduled')->expectsOutput('Another run is announcing scheduled entries.')->assertSuccessful();
    expect(announced())->toBe([]);
    $lock->release();

    artisan('sunrice:publish-scheduled')->expectsOutput('1 scheduled entry went live.');
    artisan('sunrice:publish-scheduled')->expectsOutput('0 scheduled entries went live.');
    expect(announced())->toBe(['Later']);
});

it('schedules for the moment given, whatever timezone it is given in', function () {
    config(['app.timezone' => 'Asia/Jakarta']);
    date_default_timezone_set('Asia/Jakarta');
    $this->travelTo('2026-10-19 08:00');
    // The editor sends UTC (toISOString()), an AI client or an import any offset.
    $news = scheduledNews('Later', new DateTimeImmutable('2026-10-19T02:30:00Z'));
    $other = scheduledNews('Other', new DateTimeImmutable('2026-10-19T10:30:00+08:00'));

    expect($news->fresh()?->published_at?->format('Y-m-d H:i'))->toBe('2026-10-19 09:30')
        ->and($other->fresh()?->published_at?->format('Y-m-d H:i'))->toBe('2026-10-19 09:30')
        ->and($news->fresh()?->isLive())->toBeFalse();
    $this->travelTo('2026-10-19 09:30');
    expect($news->fresh()?->isLive())->toBeTrue();
});

it('publishes the draft as stored, not as an instance loaded before someone saved', function () {
    $news = app(CreateEntry::class)->handle($this->news, ['title' => 'First']);
    $stale = EntryTranslation::query()->findOrFail($news->id);
    // Someone saves between the publish request loading it and publishing it.
    app(SaveDraft::class)->handle($news->fresh(), ['title' => 'Second']);

    app(PublishEntry::class)->handle($stale, now()->addHour());
    $news->refresh();
    expect($news->title)->toBe('Second')->and($news->draft_title)->toBe('Second')
        ->and($news->version)->toBe(3)->and($news->live_version)->toBe(3);
});
