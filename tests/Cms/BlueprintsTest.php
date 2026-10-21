<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\DeleteBlueprint;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Fieldsets\DeleteFieldset;
use Sunrice\Cms\Actions\Fieldsets\SaveFieldset;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\DefinitionValidator;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\Fieldsets;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;

beforeEach(fn () => actingAs(adminUser()));

it('creates a blueprint', function () {
    $blueprint = app(SaveBlueprint::class)->handle(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'label' => 'Intro'],
    ]]);

    expect($blueprint->fresh()?->fields())->toEqual([new Field('intro', 'textarea', 'Intro')])
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('created');

});

it('changes a blueprint', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [['handle' => 'intro', 'type' => 'textarea']]]);
    app(SaveBlueprint::class)->handle(['title' => 'News article', 'fields' => []], $blueprint);

    expect($blueprint->fresh()?->title)->toBe('News article')
        ->and($blueprint->fresh()?->fields())->toBe([])
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('updated');
});

it('keeps a blueprint\'s handle', function () {
    $blueprint = app(SaveBlueprint::class)->handle(['handle' => 'article', 'title' => 'Article', 'fields' => []]);

    app(SaveBlueprint::class)->handle(['handle' => 'post', 'title' => 'Article', 'fields' => []], $blueprint);
})->throws(ValidationException::class);

it('refuses a taken or malformed handle', function (string $handle) {
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);

    app(SaveBlueprint::class)->handle(['handle' => $handle, 'title' => 'X', 'fields' => []]);
})->with(['page', 'Page', '1st', 'with space'])->throws(ValidationException::class);

it('deletes a blueprint', function () {
    $blueprint = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    app(DeleteBlueprint::class)->handle($blueprint);

    expect(Blueprint::query()->count())->toBe(0)
        ->and(Activity::query()->latest('id')->first()?->action)->toBe('deleted');
});

it('refuses to delete a blueprint anything uses, naming what', function () {
    $page = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    $delete = fn () => app(DeleteBlueprint::class)->handle($page);
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'listing_route' => '/blog', 'listing_blueprint' => 'page']);
    Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'blueprint' => 'page']);
    GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'page']);
    Form::query()->create(['handle' => 'enquiry', 'title' => 'Enquiry', 'blueprint' => 'page']);

    expect($delete)->toThrow(ValidationException::class, 'used by the listing page of "Blog", the collection "Pages", the taxonomy "Topics", the global set "Contact", the form "Enquiry". Choose another blueprint there first.');

    Collection::query()->where('handle', 'blog')->update(['listing_blueprint' => null]);
    GlobalSet::query()->update(['blueprint' => null]);
    Form::query()->update(['blueprint' => null]);
    expect($delete)->toThrow(ValidationException::class, 'used by the collection "Pages", the taxonomy "Topics".');

    // Deleted collections and taxonomies don't count.
    Collection::query()->where('handle', 'pages')->firstOrFail()->delete();
    Taxonomy::query()->where('handle', 'topics')->firstOrFail()->delete();
    $delete();
    expect(Blueprint::query()->count())->toBe(0);
});

it('saves fieldsets and sees the change at once', function () {
    $seo = app(SaveFieldset::class)->handle(['handle' => 'seo', 'title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text']]]);
    $blueprint = app(SaveBlueprint::class)->handle(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'seo', 'type' => 'fieldset', 'config' => ['fieldset' => 'seo']]]]);
    app(SaveFieldset::class)->handle(['title' => 'SEO', 'fields' => [['handle' => 'meta_title', 'type' => 'text'], ['handle' => 'meta_image', 'type' => 'media']]], $seo);

    expect(array_map(fn (Field $f) => $f->handle, $blueprint->schema()->fields()))->toBe(['meta_title', 'meta_image']);
});

it('deletes only fieldsets nothing uses', function () {
    $quote = Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => []]);
    $seo = Fieldset::query()->create(['handle' => 'seo', 'title' => 'SEO', 'fields' => []]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'body', 'type' => 'group', 'fields' => [['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]]]],
    ]]);
    Fieldset::query()->create(['handle' => 'meta', 'title' => 'Meta', 'fields' => [['handle' => 'seo', 'type' => 'fieldset', 'config' => ['fieldset' => 'seo']]]]);

    expect(fn () => app(DeleteFieldset::class)->handle($quote))->toThrow(ValidationException::class, 'used by the blueprint "Page"')
        ->and(fn () => app(DeleteFieldset::class)->handle($seo))->toThrow(ValidationException::class, 'used by the fieldset "Meta"');

    app(DeleteFieldset::class)->handle(Fieldset::query()->where('handle', 'meta')->firstOrFail());
    app(DeleteFieldset::class)->handle($seo->fresh() ?? $seo);

    expect(Fieldset::query()->pluck('handle')->all())->toBe(['quote']);
});

it('says how to write a handle, suggesting the one meant', function () {
    try {
        app(SaveBlueprint::class)->handle(['handle' => 'contact-person', 'title' => 'Contact person', 'fields' => [['handle' => 'Job Title', 'type' => 'text']]]);
        $this->fail('Saved');
    } catch (ValidationException $e) {
        expect($e->errors()['handle'][0])->toBe('Use lowercase letters, digits and underscores, starting with a letter: try "contact_person".');
    }
    try {
        app(DefinitionValidator::class)->validate([['handle' => 'job-title', 'type' => 'text']]);
        $this->fail('Valid');
    } catch (ValidationException $e) {
        expect($e->errors()['fields.0.handle'][0])->toContain('try "job_title"');
    }
});

it('renames a blueprint and everything that names it', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.manage'));
    $blueprint = Blueprint::query()->create(['handle' => 'propety', 'title' => 'Property', 'fields' => [['handle' => 'price', 'type' => 'number']]]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page']);
    $listings = Collection::query()->create(['handle' => 'listings', 'title' => 'Listings', 'blueprint' => 'propety', 'listing_blueprint' => 'propety']);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'blueprints' => ['propety']]);
    $gone = Collection::query()->create(['handle' => 'old', 'title' => 'Old', 'blueprint' => 'propety']);
    $gone->delete();
    $taxonomy = Taxonomy::query()->create(['handle' => 'areas', 'title' => 'Areas', 'blueprint' => 'propety']);
    $set = GlobalSet::query()->create(['handle' => 'site', 'title' => 'Site', 'blueprint' => 'propety']);
    $form = Form::query()->create(['handle' => 'ask', 'title' => 'Ask', 'blueprint' => 'propety']);
    $entry = app(CreateEntry::class)->handle($pages, ['title' => 'Villa'])->entry;
    $entry->forceFill(['blueprint' => 'propety'])->save();

    Pest\Laravel\put("/admin/cms/blueprints/{$blueprint->id}", ['title' => 'Property', 'handle' => 'property', 'fields' => $blueprint->fields])->assertRedirect()->assertSessionHasNoErrors();

    expect($blueprint->fresh()->handle)->toBe('property')
        ->and($listings->fresh()->only(['blueprint', 'listing_blueprint']))->toBe(['blueprint' => 'property', 'listing_blueprint' => 'property'])
        ->and($pages->fresh()->blueprints)->toBe(['property'])
        ->and(Collection::withTrashed()->find($gone->id)->blueprint)->toBe('property')
        ->and([$taxonomy->fresh()->blueprint, $set->fresh()->blueprint, $form->fresh()->blueprint, $entry->fresh()->blueprint])->toBe(['property', 'property', 'property', 'property']);

    Pest\Laravel\put("/admin/cms/blueprints/{$blueprint->id}", ['title' => 'Property', 'handle' => 'page', 'fields' => []])->assertSessionHasErrors('handle');
    expect($blueprint->fresh()->handle)->toBe('property')->and($blueprint->fresh()->fields)->not->toBe([]);
});

it('says a fieldset\'s fields land at its level, and how to keep them apart', function () {
    Fieldset::query()->create(['handle' => 'contact_person', 'title' => 'Contact person', 'fields' => [['handle' => 'phone', 'type' => 'text'], ['handle' => 'email', 'type' => 'text']]]);
    app(Fieldsets::class)->flush();
    $hint = ' (the fieldset "contact_person" adds its fields at this level: put it in a Group to keep them under a name of their own).';
    $errors = function (array $fields): array {
        try {
            app(SaveBlueprint::class)->handle(['handle' => 'company', 'title' => 'Company', 'fields' => $fields]);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };
    $include = ['handle' => 'primary_contact', 'type' => 'fieldset', 'config' => ['fieldset' => 'contact_person']];

    expect($errors([['handle' => 'phone', 'type' => 'text'], $include]))->toBe(['fields.1.config.fieldset' => ['There is already a field "phone" here'.$hint]])
        ->and($errors([$include, ['handle' => 'email', 'type' => 'text']]))->toBe(['fields.1.handle' => ['There is already a field "email" here'.$hint]])
        ->and($errors([['handle' => 'phone', 'type' => 'text'], ['handle' => 'primary_contact', 'type' => 'group', 'fields' => [$include]]]))->toBe([]);
});
