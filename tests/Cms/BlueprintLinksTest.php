<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Links to a blueprint from where content using it is edited (the page's
 * ⋮ menu) or configured (under the blueprint's choice), for those who may
 * edit blueprints: BlueprintLinks.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    foreach (['article' => 'Article', 'review' => 'Review', 'home' => 'Home', 'topic' => 'Topic', 'tag_index' => 'Tag index', 'contact' => 'Contact', 'signup' => 'Signup'] as $handle => $title) {
        Blueprint::query()->create(['handle' => $handle, 'title' => $title, 'fields' => $handle === 'signup' ? [['handle' => 'email', 'type' => 'text']] : []]);
    }
    Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'article', 'blueprints' => ['review'], 'listing_route' => '/news', 'listing_blueprint' => 'home']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'blueprint' => 'topic', 'index_route' => '/tags', 'index_blueprint' => 'tag_index']);
    GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    Form::query()->create(['handle' => 'newsletter', 'title' => 'Newsletter', 'blueprint' => 'signup', 'message' => 'Thanks!']);
    $this->term = app(SaveTerm::class)->handle($this->tags, ['title' => 'Featured']);
});

/** Signed in with what the pages and settings need, and these permissions. */
function blueprintLinksUser(string ...$permissions): void
{
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.terms.view', 'cms.terms.create', 'cms.terms.update',
        'cms.globals.view', 'cms.globals.update', 'cms.globals.configure', 'cms.submissions.view',
        'cms.collections.view', 'cms.collections.update', 'cms.taxonomies.view', 'cms.taxonomies.update', 'cms.forms.view', 'cms.forms.update', ...$permissions));
}

/** The pages editing content with a blueprint, and the blueprint their ⋮ menu links to. */
dataset('content pages', [
    'global set values' => ['/admin/cms/globals/contact/values', 'contact'],
    'term editor' => [fn () => termEditUrl($this->term->id), 'topic'],
    'new term' => ['/admin/cms/taxonomies/tags/terms/create', 'topic'],
    'listing page' => ['/admin/cms/collections/news/listing/edit', 'home'],
    'taxonomy index page' => ['/admin/cms/taxonomies/tags/index-page/edit', 'tag_index'],
    'form submissions' => ['/admin/cms/forms/newsletter/submissions', 'signup'],
]);

it('links a content page to its blueprint for those who may edit blueprints', function (string $url, string $handle) {
    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.update');

    get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('blueprint', route('sunrice.admin.cms.blueprints.edit', $handle)));
})->with('content pages');

it('doesn\'t link a content page to its blueprint for those who may not edit blueprints', function (string $url) {
    blueprintLinksUser('cms.blueprints.view');

    get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('blueprint', null));
})->with('content pages');

it('has no blueprint link where the content has no blueprint', function () {
    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.update');
    GlobalSet::query()->where('handle', 'contact')->update(['blueprint' => null]);

    get('/admin/cms/globals/contact/values')->assertInertia(fn (Assert $page) => $page->where('blueprint', null));
});

/** The settings forms where a blueprint is chosen, and the field each one is. */
dataset('settings forms', [
    'collection: blueprint' => ['/admin/cms/collections/news/edit', 'blueprint'],
    'collection: listing blueprint' => ['/admin/cms/collections/news/edit', 'listing_blueprint'],
    'taxonomy: blueprint' => ['/admin/cms/taxonomies/tags/edit', 'blueprint'],
    'taxonomy: index page blueprint' => ['/admin/cms/taxonomies/tags/edit', 'index_blueprint'],
    'global set' => ['/admin/cms/globals/contact/edit', 'blueprint'],
    'form' => ['/admin/cms/forms/newsletter/edit', 'blueprint'],
]);

/** @return array<string, mixed> the form field named so */
function blueprintField(Assert $page, string $name): array
{
    return collect($page->toArray()['props']['sections'])->flatMap(fn (array $section) => $section['fields'])->firstWhere('name', $name);
}

it('links a blueprint choice to the chosen blueprint, or to a new one while none is chosen', function (string $url, string $name) {
    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.create', 'cms.blueprints.update');

    get($url)->assertOk()->assertInertia(fn (Assert $page) => expect(blueprintField($page, $name)['links'])->toBe([
        ['label' => 'New blueprint →', 'url' => '/admin/cms/blueprints/create', 'newTab' => false, 'showWhen' => ['field' => $name, 'value' => null, 'not' => true]],
        // The form's current value, so it follows the choice as it's edited.
        ['label' => 'Edit blueprint →', 'url' => '/admin/cms/blueprints/{'.$name.'}/edit', 'newTab' => false, 'showWhen' => ['field' => $name, 'value' => null, 'not' => false]],
    ]));
})->with('settings forms');

it('offers only the links the user may follow under a blueprint choice', function (string $url, string $name) {
    blueprintLinksUser('cms.blueprints.view');
    get($url)->assertOk()->assertInertia(fn (Assert $page) => expect(blueprintField($page, $name))->not->toHaveKey('links'));

    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.update');
    get($url)->assertOk()->assertInertia(fn (Assert $page) => expect(array_column(blueprintField($page, $name)['links'], 'label'))->toBe(['Edit blueprint →']));

    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.create');
    get($url)->assertOk()->assertInertia(fn (Assert $page) => expect(array_column(blueprintField($page, $name)['links'], 'label'))->toBe(['New blueprint →']));
})->with('settings forms');

it('links each of a collection\'s other blueprints while it\'s ticked', function () {
    blueprintLinksUser('cms.blueprints.view', 'cms.blueprints.update');

    get('/admin/cms/collections/news/edit')->assertInertia(function (Assert $page) {
        $links = collect(blueprintField($page, 'blueprints')['links'])->keyBy('label');
        expect($links['Edit blueprint: Review →'])->toBe(['label' => 'Edit blueprint: Review →', 'url' => '/admin/cms/blueprints/review/edit', 'newTab' => false, 'showWhen' => ['field' => 'blueprints', 'value' => 'review', 'not' => false]])
            // Only blueprints it can offer: not a form's or a global set's.
            ->and($links->keys()->all())->not->toContain('Edit blueprint: Signup →')->not->toContain('Edit blueprint: Contact →');
    });

    blueprintLinksUser('cms.blueprints.view');
    get('/admin/cms/collections/news/edit')->assertInertia(fn (Assert $page) => expect(blueprintField($page, 'blueprints'))->not->toHaveKey('links'));
});
