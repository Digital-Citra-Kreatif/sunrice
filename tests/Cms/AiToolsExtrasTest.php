<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Submission;
use Workbench\App\Models\User;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'listing_blueprint' => 'post', 'ordered' => true]);
    $this->hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['intro' => 'Hi']]));
});

function extrasAgent(array $permissions): string
{
    $role = Role::findOrCreate('agent-'.md5(implode(',', $permissions)), 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', ...$permissions]);
    $user = User::query()->create(['name' => 'Rina', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);

    return AiToken::issue((string) $user->id, 'Claude')[1];
}

function extrasTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

it('gives an overview of the site and checks its SEO', function () {
    $token = extrasAgent(['cms.entries.view']);
    $other = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'slug' => 'hello-again', 'seo' => ['description' => 'A description long enough to be a proper search snippet, yes.']]));

    $site = extrasTool($token, 'cms_get_site_info');
    expect($site['languages'])->toBe(['en', 'id'])->and($site['collections'][0])->toMatchArray(['handle' => 'blog', 'entries' => 2])
        ->and($site['menus'])->toBe([]);

    $audit = extrasTool($token, 'cms_seo_audit');
    expect($audit['pages_checked'])->toBe(2)
        ->and(collect($audit['pages'])->firstWhere('id', $this->hello->entry_id)['issues'])->toBe(['no description', "title also used by entry {$other->entry_id}"]);
});

it('reads and edits blueprints, menus and listing pages', function () {
    $token = extrasAgent(['cms.blueprints.manage', 'cms.menus.view', 'cms.menus.update', 'cms.entries.view', 'cms.entries.update']);

    expect(extrasTool($token, 'cms_get_blueprint')['blueprints'])->toBe([['handle' => 'post', 'title' => 'Post']])
        ->and(extrasTool($token, 'cms_get_blueprint', ['handle' => 'post'])['fields'][0]['handle'])->toBe('intro');
    extrasTool($token, 'cms_save_blueprint', ['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text'], ['handle' => 'summary', 'type' => 'textarea']]]);
    expect(Blueprint::query()->where('handle', 'post')->first()?->fields)->toHaveCount(2)
        ->and(extrasTool($token, 'cms_save_blueprint', ['handle' => 'hero', 'kind' => 'fieldset', 'title' => 'Hero', 'fields' => []]))->toMatchArray(['created' => true])
        ->and(extrasTool($token, 'cms_save_blueprint', ['handle' => 'post', 'title' => '', 'fields' => []]))->toContain('Not saved');

    Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    extrasTool($token, 'cms_save_menu', ['menu' => 'main', 'items' => [['entry_id' => $this->hello->entry_id, 'children' => [['url' => 'https://example.com', 'labels' => ['en' => 'Out']]]]]]);
    $menu = extrasTool($token, 'cms_get_menu', ['menu' => 'main']);
    expect($menu['items'][0]['entry_id'])->toBe($this->hello->entry_id)->and($menu['items'][0]['children'][0]['url'])->toBe('https://example.com')
        ->and(extrasTool($token, 'cms_save_menu', ['menu' => 'main', 'items' => [['url' => 'javascript:alert(1)', 'labels' => ['en' => 'x']]]]))->toContain('Not saved');

    extrasTool($token, 'cms_save_listing', ['collection' => 'blog', 'data' => ['intro' => 'All our posts']]);
    expect(extrasTool($token, 'cms_get_listing', ['collection' => 'blog'])['data']['intro'])->toBe('All our posts');
});

it('lists and restores revisions, moves entries, and deletes submissions', function () {
    $token = extrasAgent(['cms.entries.view', 'cms.entries.update', 'cms.submissions.view', 'cms.submissions.delete']);
    app(SaveDraft::class)->handle($this->hello->fresh() ?? $this->hello, ['title' => 'Hello again']);
    $second = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Second']));

    $revisions = extrasTool($token, 'cms_entry_revisions', ['id' => $this->hello->entry_id]);
    expect($revisions)->toHaveCount(1)->and($revisions[0]['title'])->toBe('Hello');
    expect(extrasTool($token, 'cms_entry_revisions', ['id' => $this->hello->entry_id, 'restore' => $revisions[0]['id']])['title'])->toBe('Hello');

    expect(extrasTool($token, 'cms_move_entry', ['id' => $second->entry_id, 'direction' => 'up']))->toBe(['moved' => 'up']);

    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    $submission = Submission::query()->create(['form_id' => $form->id, 'locale' => 'en', 'data' => []]);
    expect(extrasTool($token, 'cms_delete_submissions', ['ids' => [$submission->id]]))->toBe(['deleted' => 1, 'not_allowed' => 0])
        ->and(Submission::query()->count())->toBe(0);

    $viewer = extrasAgent(['cms.entries.view']);
    expect(extrasTool($viewer, 'cms_move_entry', ['id' => $second->entry_id, 'direction' => 'down']))->toContain('permission')
        ->and(extrasTool($viewer, 'cms_get_blueprint'))->toContain('permission');
});
