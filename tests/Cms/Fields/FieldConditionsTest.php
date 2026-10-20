<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(adminUser());
});

it('shows a field by another field\'s value, as the editor does', function () {
    $field = fn (string $operator, mixed $value = null) => Field::fromArray(['handle' => 'video', 'type' => 'text', 'condition' => ['field' => 'layout', 'operator' => $operator, 'value' => $value]]);

    expect($field('equals', 'video')->isShownWith(['layout' => 'video']))->toBeTrue()
        ->and($field('equals', 'video')->isShownWith(['layout' => 'image']))->toBeFalse()
        ->and($field('equals', 'video')->isShownWith(['layout' => ['image', 'video']]))->toBeTrue()
        ->and($field('not_equals', 'video')->isShownWith(['layout' => 'image']))->toBeTrue()
        ->and($field('equals', 'true')->isShownWith(['layout' => true]))->toBeTrue()
        ->and($field('filled')->isShownWith(['layout' => false]))->toBeFalse()
        ->and($field('empty')->isShownWith([]))->toBeTrue()
        ->and(Field::fromArray(['handle' => 'x', 'type' => 'text', 'condition' => ['field' => 'y', 'operator' => 'bogus']])->condition)->toBeNull()
        ->and($field('equals', 'video')->toArray()['condition'])->toBe(['field' => 'layout', 'operator' => 'equals', 'value' => 'video'])
        ->and(Field::fromArray(['handle' => 'x', 'type' => 'text'])->toArray())->not->toHaveKey('condition');
});

it('asks nothing of a hidden field, also inside groups', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'layout', 'type' => 'select', 'config' => ['options' => [['value' => 'image', 'label' => 'Image'], ['value' => 'video', 'label' => 'Video']]]],
        ['handle' => 'video_url', 'type' => 'text', 'required' => true, 'condition' => ['field' => 'layout', 'operator' => 'equals', 'value' => 'video']],
        ['handle' => 'hero', 'type' => 'group', 'fields' => [
            ['handle' => 'show', 'type' => 'toggle'],
            ['handle' => 'title', 'type' => 'text', 'required' => true, 'condition' => ['field' => 'show', 'operator' => 'filled']],
        ]],
    ]]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);

    $entry = app(CreateEntry::class)->handle($blog, ['title' => 'Image post', 'data' => ['layout' => 'image', 'hero' => ['show' => false]]]);
    expect($entry->draft_data['layout'])->toBe('image');

    expect(fn () => app(CreateEntry::class)->handle($blog, ['title' => 'Video post', 'data' => ['layout' => 'video']]))
        ->toThrow(ValidationException::class, 'Video Url');
    expect(fn () => app(CreateEntry::class)->handle($blog, ['title' => 'Hero post', 'data' => ['layout' => 'image', 'hero' => ['show' => true]]]))
        ->toThrow(ValidationException::class);
});

it('keeps a hidden field\'s value, still checked', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'layout', 'type' => 'text'],
        ['handle' => 'video_url', 'type' => 'text', 'required' => true, 'validation' => ['url'], 'condition' => ['field' => 'layout', 'operator' => 'equals', 'value' => 'video']],
    ]]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);

    $entry = app(CreateEntry::class)->handle($blog, ['title' => 'Post', 'data' => ['layout' => 'image', 'video_url' => 'https://example.com/v']]);
    expect($entry->draft_data['video_url'])->toBe('https://example.com/v');
    expect(fn () => app(CreateEntry::class)->handle($blog, ['title' => 'Other', 'data' => ['layout' => 'image', 'video_url' => 'not a url']]))
        ->toThrow(ValidationException::class);
});

it('checks conditions in blueprints', function () {
    $save = fn (array $condition) => app(SaveBlueprint::class)->handle(['handle' => 'x'.uniqid(), 'title' => 'X', 'fields' => [
        ['handle' => 'layout', 'type' => 'text'],
        ['handle' => 'video', 'type' => 'text', 'condition' => $condition],
    ]]);

    expect($save(['field' => 'layout', 'operator' => 'equals', 'value' => 'video'])->fields[1]['condition'])->toBe(['field' => 'layout', 'operator' => 'equals', 'value' => 'video'])
        ->and(fn () => $save(['field' => 'nope', 'operator' => 'equals', 'value' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $save(['field' => 'video', 'operator' => 'equals', 'value' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $save(['field' => 'layout', 'operator' => 'like', 'value' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $save(['field' => 'layout', 'operator' => 'equals', 'value' => ['x']]))->toThrow(ValidationException::class);
});
