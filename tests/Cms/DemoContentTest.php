<?php

declare(strict_types=1);

use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(fn () => config(['sunrice-cms.locales' => ['en', 'id']]));

it('seeds a demo site through the actions', function () {
    artisan('sunrice:demo-content')->assertSuccessful();

    expect(Collection::query()->pluck('handle')->sort()->values()->all())->toBe(['blog', 'pages'])
        ->and(Entry::query()->count())->toBe(12)
        ->and(EntryTranslation::query()->count())->toBe(24)
        ->and(EntryTranslation::query()->where('status', EntryTranslation::PUBLISHED)->count())->toBe(22)
        ->and(Term::query()->count())->toBe(4)
        ->and(Term::query()->whereNotNull('parent_id')->count())->toBe(2)
        ->and(Fieldset::query()->count())->toBe(2)
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuItem::query()->count())->toBe(7)
        ->and(GlobalSet::query()->first()?->values()->count())->toBe(2)
        ->and(Form::query()->where('handle', 'contact')->exists())->toBeTrue()
        ->and(Redirect::query()->count())->toBe(2);

    $post = EntryTranslation::query()->where('draft_slug', 'hello-world')->where('locale', 'en')->first();
    expect($post->data['sections'][1]['type'])->toBe('quote_block')
        ->and($post->data['topics'])->toHaveCount(1)
        ->and(Entry::query()->whereNotNull('parent_id')->count())->toBe(1);
    get('/blog/hello-world')->assertOk()->assertSee('Hello, world');
    get('/id/blog/hello-world')->assertOk()->assertSee('Halo, dunia');
});

it('adds only what is missing when run again, and starts over with --fresh', function () {
    artisan('sunrice:demo-content')->assertSuccessful();
    artisan('sunrice:demo-content')->expectsOutputToContain('already there')->assertSuccessful();
    expect(Entry::query()->count())->toBe(12);

    artisan('sunrice:demo-content', ['--fresh' => true])->assertSuccessful();
    expect(Entry::withTrashed()->count())->toBe(12)
        ->and(Term::withTrashed()->count())->toBe(4)
        ->and(Collection::withTrashed()->count())->toBe(2);
});
