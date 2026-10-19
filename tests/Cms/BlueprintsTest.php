<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Cms\Actions\Blueprints\DeleteBlueprint;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Fieldsets\DeleteFieldset;
use Sunrice\Cms\Actions\Fieldsets\SaveFieldset;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Fieldset;

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
