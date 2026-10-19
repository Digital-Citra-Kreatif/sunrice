<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Submission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['inertia.testing.ensure_pages_exist' => false]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'post']);
    actingAs(adminUser());
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello']);
    app(CreateEntry::class)->handle($this->news, ['title' => 'Breaking']);
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    Submission::query()->create(['form_id' => $form->id, 'locale' => 'en', 'data' => []]);
});

it('shows content, recent edits and submissions, as far as the user may see', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.blog.view'));

    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('cards', fn ($cards) => collect($cards)->pluck('key')->all() === ['cms.collections', 'cms.recent'])
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('content', function ($content): bool {
            $content = collect($content)->toArray();
            expect($content['cms.collections']['items'])->toBe([['label' => 'Blog', 'value' => '1', 'url' => '/admin/cms/collections/blog/entries']])
                ->and(array_column($content['cms.recent']['items'], 'title'))->toBe(['Hello']);

            return true;
        })));

    actingAs(adminUser(Panel::ACCESS, 'cms.submissions.view', 'cms.comments.view'));
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('cards', fn ($cards) => collect($cards)->pluck('key')->all() === ['cms.submissions', 'cms.comments'])
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('content', function ($content): bool {
            $content = collect($content)->toArray();
            expect($content['cms.submissions']['items'][0])->toMatchArray(['title' => 'Contact', 'url' => '/admin/cms/forms/contact/submissions'])
                ->and($content['cms.comments']['items'][0])->toMatchArray(['label' => 'Waiting for approval', 'value' => '0']);

            return true;
        })));
});
