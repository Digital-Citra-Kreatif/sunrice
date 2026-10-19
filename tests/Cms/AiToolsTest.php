<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;
use Workbench\App\Models\User;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text', 'required' => true],
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    $this->hello = app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['intro' => 'Hi']]);
});

/** A token for a user with these permissions (and panel and AI access). */
function cmsAgent(array $permissions, bool $readOnly = false): string
{
    $role = Role::findOrCreate('agent-'.md5(implode(',', $permissions)), 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', ...$permissions]);
    $user = User::query()->create(['name' => 'Rina', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);

    return AiToken::issue((string) $user->id, 'Claude', readOnly: $readOnly)[1];
}

/** Call a tool; the decoded JSON it returned, or the error text. */
function cmsTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

it('lists collections and entries, and reads an entry with its fields', function () {
    $token = cmsAgent(['cms.entries.view']);

    expect(cmsTool($token, 'cms_list_entries')['collections'][0])->toMatchArray(['handle' => 'blog', 'route' => '/blog/{slug}', 'entries' => 1]);

    $list = cmsTool($token, 'cms_list_entries', ['collection' => 'blog', 'search' => 'hel']);
    expect($list['total'])->toBe(1)
        ->and($list['entries'][0])->toMatchArray(['id' => $this->hello->entry_id, 'title' => 'Hello', 'status' => 'draft', 'languages' => ['en' => 'draft', 'id' => null]]);

    $entry = cmsTool($token, 'cms_get_entry', ['id' => $this->hello->entry_id]);
    expect($entry['values'])->toMatchArray(['title' => 'Hello', 'slug' => 'hello', 'data' => ['intro' => 'Hi', 'tags' => []]])
        ->and(array_column($entry['fields'], 'handle'))->toBe(['intro', 'tags'])
        ->and(cmsTool($token, 'cms_get_entry', ['collection' => 'blog'])['defaults'])->toHaveKey('intro')
        ->and(cmsTool($token, 'cms_get_entry', ['id' => 999]))->toContain('No such entry');
});

it('writes drafts and publishes them, as the editor does', function () {
    $token = cmsAgent(['cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish', 'cms.terms.manage']);
    $tag = cmsTool($token, 'cms_save_term', ['taxonomy' => 'tags', 'title' => 'News']);
    expect($tag)->toContain('taxonomy');
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $tag = cmsTool($token, 'cms_save_term', ['taxonomy' => 'tags', 'title' => 'News']);
    expect(cmsTool($token, 'cms_list_terms', ['taxonomy' => 'tags'])['terms'][0])->toMatchArray(['id' => $tag['id'], 'translations' => ['en' => ['title' => 'News', 'slug' => 'news']]]);

    $created = cmsTool($token, 'cms_save_entry', ['collection' => 'blog', 'title' => 'From the agent', 'data' => ['intro' => 'Written by AI', 'tags' => [$tag['id']]], 'seo' => ['description' => 'Short']]);
    expect($created)->toMatchArray(['saved' => true, 'title' => 'From the agent', 'slug' => 'from-the-agent', 'status' => 'draft']);

    expect(cmsTool($token, 'cms_save_entry', ['id' => $created['id'], 'data' => ['intro' => '']]))->toContain('Intro');
    $saved = cmsTool($token, 'cms_save_entry', ['id' => $created['id'], 'data' => ['intro' => 'Better'], 'publish' => true]);
    expect($saved)->toMatchArray(['status' => 'published', 'url' => url('/blog/from-the-agent')]);

    $translation = EntryTranslation::query()->where('entry_id', $created['id'])->sole();
    expect($translation->data)->toMatchArray(['intro' => 'Better', 'tags' => [$tag['id']]])
        ->and($translation->data['seo']['description'])->toBe('Short')
        ->and($translation->entry->terms->pluck('id')->all())->toBe([$tag['id']]);
    expect(Activity::query()->where('subject_type', 'entry')->where('source', 'ai')->pluck('action')->all())->toContain('created', 'updated', 'published');
});

it('translates, schedules, unpublishes and trashes', function () {
    $token = cmsAgent(['cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish', 'cms.entries.delete']);
    $id = $this->hello->entry_id;

    expect(cmsTool($token, 'cms_translate_entry', ['id' => $id, 'locale' => 'id']))->toMatchArray(['locale' => 'id', 'status' => 'draft']);
    cmsTool($token, 'cms_save_entry', ['id' => $id, 'locale' => 'id', 'title' => 'Halo', 'slug' => 'halo']);
    expect(cmsTool($token, 'cms_publish_entry', ['id' => $id, 'locale' => 'id']))->toMatchArray(['status' => 'published', 'url' => url('/id/blog/halo')]);
    expect(cmsTool($token, 'cms_publish_entry', ['id' => $id, 'published_at' => now()->addWeek()->toIso8601String()]))->toMatchArray(['status' => 'scheduled', 'locale' => 'en']);
    expect(cmsTool($token, 'cms_publish_entry', ['id' => $id, 'published_at' => 'whenever']))->toContain('published_at');
    expect(cmsTool($token, 'cms_unpublish_entry', ['id' => $id, 'locale' => 'id']))->toMatchArray(['status' => 'draft']);
    expect(cmsTool($token, 'cms_get_entry', ['id' => $id, 'locale' => 'fr']))->toContain('no fr translation');

    expect(cmsTool($token, 'cms_trash_entry', ['id' => $id]))->toBe(['trashed' => true, 'id' => $id])
        ->and(cmsTool($token, 'cms_list_entries', ['collection' => 'blog', 'trashed' => true])['total'])->toBe(1)
        ->and(cmsTool($token, 'cms_save_entry', ['id' => $id, 'title' => 'X']))->toContain('No such entry')
        ->and(cmsTool($token, 'cms_trash_entry', ['id' => $id, 'restore' => true]))->toBe(['restored' => true, 'id' => $id]);
});

it('checks permissions, and gives read-only tokens only the reading tools', function () {
    $viewer = cmsAgent(['cms.entries.view']);
    expect(cmsTool($viewer, 'cms_save_entry', ['collection' => 'blog', 'title' => 'X']))->toContain('permission to create entries')
        ->and(cmsTool($viewer, 'cms_save_entry', ['id' => $this->hello->entry_id, 'title' => 'X']))->toContain('permission to edit entries')
        ->and(cmsTool($viewer, 'cms_publish_entry', ['id' => $this->hello->entry_id]))->toContain('permission to publish')
        ->and(cmsTool($viewer, 'cms_trash_entry', ['id' => $this->hello->entry_id]))->toContain('permission to trash')
        ->and(cmsTool($viewer, 'cms_get_globals'))->toContain('permission')
        ->and(cmsTool($viewer, 'cms_list_submissions'))->toContain('permission');
    expect(cmsTool(cmsAgent([]), 'cms_list_entries'))->toContain('permission to view entries');

    $readOnly = cmsAgent(['cms.entries.view', 'cms.entries.update'], readOnly: true);
    $tools = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Authorization' => "Bearer {$readOnly}"])->json('result.tools.*.name');
    expect($tools)->toContain('cms_list_entries', 'cms_get_entry', 'cms_list_terms', 'cms_get_globals', 'cms_list_submissions')
        ->not->toContain('cms_save_entry', 'cms_publish_entry', 'cms_trash_entry', 'cms_save_globals');
    expect(EntryTranslation::query()->find($this->hello->id)?->draft_title)->toBe('Hello');
});

it('reads and saves globals', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'phone', 'type' => 'text']]]);
    GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    $token = cmsAgent(['cms.globals.view', 'cms.globals.update']);

    expect(cmsTool($token, 'cms_get_globals')['sets'][0]['handle'])->toBe('contact');
    expect(cmsTool($token, 'cms_save_globals', ['set' => 'contact', 'data' => ['phone' => '+62 21 555']]))->toMatchArray(['saved' => true, 'locale' => 'en'])
        ->and(cmsTool($token, 'cms_get_globals', ['set' => 'contact'])['values'])->toBe(['phone' => '+62 21 555']);
});

it('lists form submissions', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text', 'label' => 'Your name']]]);
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SubmitForm::class)->handle($form, ['name' => 'Ana']);
    $token = cmsAgent(['cms.submissions.view']);

    expect(cmsTool($token, 'cms_list_submissions')['forms'][0])->toMatchArray(['handle' => 'contact', 'submissions' => 1])
        ->and(cmsTool($token, 'cms_list_submissions', ['form' => 'contact'])['submissions'][0]['values'])->toBe(['Your name' => 'Ana']);
});
