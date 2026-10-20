<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Forms\SubmitForm;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\SiteSettings;
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
        ->and(cmsTool($viewer, 'cms_save_entry', ['id' => $this->hello->entry_id, 'title' => 'X']))->toContain('permission to edit this entry')
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

it('lists, shows and restores an entry\'s revisions', function () {
    $token = cmsAgent(['cms.entries.view', 'cms.entries.update', 'cms.entries.publish']);
    $id = $this->hello->entry_id;
    cmsTool($token, 'cms_publish_entry', ['id' => $id]);
    cmsTool($token, 'cms_save_entry', ['id' => $id, 'title' => 'Hello again', 'publish' => true]);

    $list = cmsTool($token, 'cms_entry_revisions', ['id' => $id]);
    expect(array_column($list['revisions'], 'title'))->toBe(['Hello again', 'Hello'])
        ->and($list['revisions'][0]['by'])->toBe('Rina');
    $first = $list['revisions'][1]['revision_id'];
    expect(cmsTool($token, 'cms_entry_revisions', ['action' => 'show', 'revision_id' => $first]))->toMatchArray(['title' => 'Hello', 'slug' => 'hello', 'data' => ['intro' => 'Hi', 'tags' => []]])
        ->and(cmsTool($token, 'cms_entry_revisions', ['action' => 'show']))->toContain('revision id')
        ->and(cmsTool($token, 'cms_entry_revisions', ['action' => 'show', 'revision_id' => 999]))->toContain('No such revision');

    expect(cmsTool(cmsAgent(['cms.entries.view']), 'cms_entry_revisions', ['action' => 'restore', 'revision_id' => $first]))->toContain('permission to edit this entry');
    expect(cmsTool($token, 'cms_entry_revisions', ['action' => 'restore', 'revision_id' => $first]))->toMatchArray(['restored' => true, 'title' => 'Hello', 'has_unpublished_changes' => true])
        ->and(Activity::query()->where('action', 'reverted')->sole()->source)->toBe('ai');
});

it('orders entries, terms and menu items', function () {
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}', 'ordered' => true]);
    $ids = collect(['A', 'B', 'C'])->map(fn (string $t) => app(CreateEntry::class)->handle($pages, ['title' => $t, 'data' => ['intro' => 'x']])->entry_id)->all();
    $editor = cmsAgent(['cms.entries.view', 'cms.entries.update', 'cms.terms.manage', 'cms.menus.view', 'cms.menus.update']);

    expect(cmsTool(cmsAgent(['cms.entries.view']), 'cms_reorder', ['type' => 'entries', 'in' => 'pages', 'ids' => [$ids[2]]]))->toContain('permission to order entries')
        ->and(cmsTool($editor, 'cms_reorder', ['type' => 'entries', 'in' => 'pages', 'ids' => [$ids[2], $ids[0]]]))->toBe(['saved' => true, 'order' => [$ids[2], $ids[0], $ids[1]]])
        ->and(cmsTool($editor, 'cms_reorder', ['type' => 'entries', 'in' => 'pages', 'ids' => [$ids[0], $ids[0]]]))->toContain('each entry id once')
        ->and(cmsTool($editor, 'cms_reorder', ['type' => 'entries', 'in' => 'pages', 'ids' => [$this->hello->entry_id]]))->toContain('Not entries of this collection')
        ->and(cmsTool($editor, 'cms_reorder', ['type' => 'entries', 'in' => 'blog', 'ids' => [$this->hello->entry_id]]))->toContain('Manual order')
        ->and(Activity::query()->where('action', 'reordered')->where('subject_type', 'collection')->count())->toBe(1);

    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $terms = collect(['News', 'Tips'])->map(fn (string $t) => cmsTool($editor, 'cms_save_term', ['taxonomy' => 'tags', 'title' => $t])['id'])->all();
    expect(cmsTool($editor, 'cms_reorder', ['type' => 'terms', 'in' => 'tags', 'ids' => [$terms[1]]])['order'])->toBe([$terms[1], $terms[0]])
        ->and(cmsTool($editor, 'cms_reorder', ['type' => 'terms', 'in' => 'tags', 'ids' => [$terms[0]], 'parent_id' => $terms[1]]))->toContain('no parents');

    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['url' => '/a', 'labels' => ['en' => 'A']], ['url' => '/b', 'labels' => ['en' => 'B']]]);
    $items = $menu->items()->pluck('id')->all();
    expect(cmsTool($editor, 'cms_reorder', ['type' => 'menu_items', 'in' => 'main', 'ids' => [$items[1], $items[0]]])['order'])->toBe([$items[1], $items[0]])
        ->and(array_column(app(Menus::class)->get('main', 'en'), 'label'))->toBe(['B', 'A']);
});

it('reads and saves listing pages', function () {
    Blueprint::query()->create(['handle' => 'listing', 'title' => 'Listing', 'fields' => [['handle' => 'heading', 'type' => 'text'], ['handle' => 'count', 'type' => 'number', 'translatable' => false]]]);
    $token = cmsAgent(['cms.entries.view', 'cms.entries.update']);
    expect(cmsTool($token, 'cms_get_listing', ['collection' => 'blog']))->toContain('no listing page');
    $this->blog->update(['listing_route' => '/blog', 'listing_blueprint' => 'listing']);

    $listing = cmsTool($token, 'cms_get_listing', ['collection' => 'blog']);
    expect($listing)->toMatchArray(['url' => url('/blog'), 'version' => null])->and(array_column($listing['fields'], 'handle'))->toBe(['heading', 'count']);
    $saved = cmsTool($token, 'cms_save_listing', ['collection' => 'blog', 'data' => ['heading' => 'News', 'count' => 3]]);
    expect($saved['values'])->toBe(['heading' => 'News', 'count' => 3]);
    expect(cmsTool($token, 'cms_save_listing', ['collection' => 'blog', 'locale' => 'id', 'data' => ['heading' => 'Berita']])['values'])->toBe(['heading' => 'Berita', 'count' => 3])
        ->and(cmsTool($token, 'cms_save_listing', ['collection' => 'blog', 'data' => ['heading' => 'Old'], 'version' => $saved['version'] - 1]))->toContain('Someone else saved this')
        ->and(cmsTool(cmsAgent(['cms.entries.view']), 'cms_save_listing', ['collection' => 'blog', 'data' => []]))->toContain('permission to edit the listing page');
});

it('reads and saves blueprints and fieldsets, with the field types', function () {
    expect(cmsTool(cmsAgent(['cms.entries.view']), 'cms_get_blueprint'))->toContain('permission to manage blueprints');
    $token = cmsAgent(['cms.blueprints.manage']);

    expect(array_column(cmsTool($token, 'cms_field_types')['fieldTypes'], 'type'))->toContain('text', 'rich_text', 'media', 'terms')
        ->and(cmsTool($token, 'cms_get_blueprint')['blueprints'][0])->toBe(['handle' => 'post', 'title' => 'Post', 'fields' => 2, 'collections' => ['blog']])
        ->and(cmsTool($token, 'cms_get_blueprint', ['handle' => 'post'])['fields'][0])->toMatchArray(['handle' => 'intro', 'type' => 'text']);

    $saved = cmsTool($token, 'cms_save_blueprint', ['handle' => 'event', 'title' => 'Event', 'fields' => [['handle' => 'starts', 'type' => 'date', 'required' => true]]]);
    expect($saved)->toMatchArray(['saved' => true, 'created' => true, 'handle' => 'event'])
        ->and(cmsTool($token, 'cms_save_blueprint', ['handle' => 'event', 'fields' => [['handle' => 'starts', 'type' => 'nope']]]))->toContain('type')
        ->and(cmsTool($token, 'cms_save_blueprint', ['handle' => 'event', 'fields' => [['handle' => 'title', 'type' => 'text']]]))->toContain('used by the content')
        ->and(cmsTool($token, 'cms_save_blueprint', ['handle' => 'event', 'fields' => [['handle' => 'venue', 'type' => 'text']]]))->toMatchArray(['created' => false, 'title' => 'Event'])
        ->and(cmsTool($token, 'cms_save_blueprint', ['kind' => 'fieldset', 'handle' => 'hero', 'fields' => [['handle' => 'headline', 'type' => 'text']]]))->toMatchArray(['kind' => 'fieldset', 'title' => 'Hero'])
        ->and(cmsTool($token, 'cms_get_blueprint', ['kind' => 'fieldset'])['fieldsets'][0]['handle'])->toBe('hero')
        ->and(Activity::query()->where('subject_type', 'blueprint')->where('source', 'ai')->pluck('action')->all())->toBe(['created', 'updated']);
});

it('reads and saves menus', function () {
    Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    expect(cmsTool(cmsAgent(['cms.entries.view']), 'cms_get_menu'))->toContain('permission to view menus');
    $token = cmsAgent(['cms.menus.view', 'cms.menus.update']);
    app(PublishEntry::class)->handle($this->hello);

    $saved = cmsTool($token, 'cms_save_menu', ['handle' => 'main', 'items' => [
        ['entry_id' => $this->hello->entry_id, 'children' => [['url' => 'https://example.org', 'labels' => ['en' => 'Elsewhere'], 'new_tab' => true]]],
    ]]);
    expect($saved['items'][0])->toMatchArray(['entry_id' => $this->hello->entry_id, 'labels' => []])
        ->and($saved['items'][0]['children'][0])->toMatchArray(['url' => 'https://example.org', 'new_tab' => true]);
    $menu = cmsTool($token, 'cms_get_menu', ['handle' => 'main']);
    expect($menu['site'][0])->toMatchArray(['label' => 'Hello', 'url' => url('/blog/hello')])
        ->and(cmsTool($token, 'cms_save_menu', ['handle' => 'main', 'items' => $menu['items']])['items'][0]['children'][0]['labels'])->toBe(['en' => 'Elsewhere'])
        ->and(cmsTool($token, 'cms_save_menu', ['handle' => 'main', 'items' => [['url' => '/x']]]))->toContain('required')
        ->and(cmsTool($token, 'cms_save_menu', ['handle' => 'main', 'items' => [['url' => 'javascript:alert(1)', 'labels' => ['en' => 'X']]]]))->toContain('Use a web address')
        ->and(cmsTool($token, 'cms_get_menu')['menus'])->toBe([['handle' => 'main', 'title' => 'Main']]);
});

it('deletes form submissions with their permission', function () {
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    $form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    $one = app(SubmitForm::class)->handle($form, ['name' => 'Ana']);
    $two = app(SubmitForm::class)->handle($form, ['name' => 'Budi']);

    expect(cmsTool(cmsAgent(['cms.submissions.view']), 'cms_delete_submissions', ['form' => 'contact', 'ids' => [$one->id]]))->toContain('permission to delete submissions');
    expect(cmsTool(cmsAgent(['cms.submissions.view', 'cms.submissions.delete']), 'cms_delete_submissions', ['form' => 'contact', 'ids' => [$one->id, 999]]))->toBe(['deleted' => [$one->id], 'not_found' => [999]])
        ->and($form->submissions()->pluck('id')->all())->toBe([$two->id])
        ->and(Activity::query()->where('subject_type', 'submissions')->where('action', 'deleted')->sole()->source)->toBe('ai');
});

it('deletes for good, duplicates and sets the homepage', function () {
    $token = cmsAgent(['cms.entries.view', 'cms.entries.create', 'cms.entries.delete']);
    $id = $this->hello->entry_id;

    $copy = cmsTool($token, 'cms_manage_entry', ['id' => $id, 'action' => 'duplicate']);
    expect($copy)->toMatchArray(['duplicated' => true, 'from' => $id, 'title' => 'Hello (copy)', 'status' => 'draft']);
    expect(cmsTool($token, 'cms_manage_entry', ['id' => $copy['id'], 'action' => 'delete']))->toContain('trash first');
    cmsTool($token, 'cms_trash_entry', ['id' => $copy['id']]);
    expect(cmsTool($token, 'cms_manage_entry', ['id' => $copy['id'], 'action' => 'delete']))->toBe(['deleted' => true, 'id' => $copy['id'], 'title' => 'Hello (copy)'])
        ->and(Entry::withTrashed()->find($copy['id']))->toBeNull();

    expect(cmsTool($token, 'cms_manage_entry', ['id' => $id, 'action' => 'set_homepage']))->toContain('permission to change the site settings');
    expect(cmsTool(cmsAgent(['cms.entries.view', 'cms.settings.site']), 'cms_manage_entry', ['id' => $id, 'action' => 'set_homepage']))->toMatchArray(['homepage' => true, 'id' => $id])
        ->and(app(SiteSettings::class)->homepage)->toBe($id)
        ->and(cmsTool($token, 'cms_manage_entry', ['id' => $id, 'action' => 'explode']))->toContain('action');
});

it('audits SEO page by page', function () {
    app(PublishEntry::class)->handle($this->hello);
    $other = app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'slug' => 'hello-two', 'data' => ['intro' => 'Hi'], 'seo' => ['description' => str_repeat('Words that describe the page. ', 3)]]);
    app(PublishEntry::class)->handle($other);
    $token = cmsAgent(['cms.entries.view']);

    $audit = cmsTool($token, 'cms_seo_audit');
    expect($audit)->toMatchArray(['locale' => 'en', 'checked' => 2, 'page' => 1, 'last_page' => 1, 'duplicate_titles' => [['title' => 'Hello', 'pages' => 2]], 'images_without_alt' => 0]);
    $hello = collect($audit['pages_with_issues'])->firstWhere('id', $this->hello->entry_id);
    expect(implode(' ', $hello['issues']))->toContain('meta title is short', 'No meta description', 'No share image', 'Thin content', 'Not live in id')
        ->and(implode(' ', collect($audit['pages_with_issues'])->firstWhere('id', $other->entry_id)['issues']))->not->toContain('No meta description');
    expect(cmsTool($token, 'cms_seo_audit', ['collection' => 'nope']))->toContain('No such collection')
        ->and(cmsTool($token, 'cms_seo_audit', ['locale' => 'id'])['checked'])->toBe(0);
});

it('renders public pages, not the panel', function () {
    app(PublishEntry::class)->handle($this->hello);
    $token = cmsAgent(['cms.entries.view']);

    $page = cmsTool($token, 'cms_render_page', ['path' => '/blog/hello']);
    expect($page)->toMatchArray(['status' => 200, 'error' => null, 'truncated' => false])->and($page['html'])->toContain('<h1>Hello</h1>');
    expect(cmsTool($token, 'cms_render_page', ['path' => '/blog/nope'])['status'])->toBe(404)
        ->and(cmsTool($token, 'cms_render_page', ['path' => '/admin/users']))->toContain('public pages')
        ->and(cmsTool($token, 'cms_render_page', ['path' => '/MCP']))->toContain('public pages')
        ->and(cmsTool($token, 'cms_render_page', ['path' => 'https://evil.test/']))->toContain('path')
        ->and(cmsTool($token, 'cms_render_page', ['path' => '//evil.test/']))->toContain('path');
    // The agent is still signed in afterwards.
    expect(cmsTool($token, 'cms_list_entries')['collections'][0]['handle'])->toBe('blog');
});

it('writes templates only for super admins when the site allows it, checked and backed up', function () {
    $views = storage_path('framework/testing/ai-views');
    File::deleteDirectory($views);
    File::ensureDirectoryExists($views);
    config(['view.paths' => [$views]]);
    View::addLocation($views);
    app(PublishEntry::class)->handle($this->hello);

    expect(cmsTool(cmsAgent(['cms.entries.view']), 'cms_get_template_guide'))->toContain('permission');
    $builder = cmsAgent(['cms.blueprints.manage']);
    $guide = cmsTool($builder, 'cms_get_template_guide');
    expect($guide['guide'])->toContain('resources/views/cms')
        ->and($guide['current']['collections']['blog']['entry_page']['uses'])->toBe('sunrice-cms::entry')
        ->and($guide['can_write'])->toBeFalse()
        ->and(cmsTool($builder, 'cms_write_template', ['path' => 'blog.blade.php', 'content' => 'x']))->toContain('super admin')
        ->and(cmsTool($builder, 'cms_read_template', ['path' => 'entry.blade.php', 'source' => 'default'])['content'])->toContain('x-sunrice-cms::seo');

    $role = SyncPermissions::superAdminRole();
    Role::findOrCreate($role, 'web');
    $admin = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x'])->assignRole($role);
    [, $super] = AiToken::issue((string) $admin->id, 'Claude');
    expect(cmsTool($super, 'cms_write_template', ['path' => 'blog.blade.php', 'content' => 'x']))->toContain('sunrice-cms.ai.templates');
    config(['sunrice-cms.ai.templates' => true]);

    expect(cmsTool($super, 'cms_write_template', ['path' => 'blog.blade.php', 'content' => '@if($x) <p>']))->toContain('syntax error')
        ->and(cmsTool($super, 'cms_write_template', ['path' => '../evil.blade.php', 'content' => 'x']))->toContain('Give a path')
        ->and(cmsTool($super, 'cms_write_template', ['path' => 'blog.php', 'content' => 'x']))->toContain('Give a path')
        ->and(cmsTool($super, 'cms_read_template', ['path' => 'a/../../x.blade.php']))->toContain('Give a path')
        ->and(File::exists($views.'/cms/blog.blade.php'))->toBeFalse();

    expect(cmsTool($super, 'cms_write_template', ['path' => 'blog.blade.php', 'content' => '<h1 class="post">{{ $title }}</h1>']))->toMatchArray(['saved' => true, 'created' => true, 'view' => 'cms.blog', 'backup' => null])
        ->and(cmsTool($super, 'cms_render_page', ['path' => '/blog/hello'])['html'])->toBe('<h1 class="post">Hello</h1>');
    $replaced = cmsTool($super, 'cms_write_template', ['path' => 'blog.blade.php', 'content' => '<h2>{{ $title }}</h2>']);
    expect($replaced['created'])->toBeFalse()
        ->and(File::get(base_path($replaced['backup'])))->toBe('<h1 class="post">{{ $title }}</h1>')
        ->and(cmsTool($super, 'cms_read_template', ['path' => 'blog.blade.php'])['content'])->toBe('<h2>{{ $title }}</h2>')
        ->and(cmsTool($super, 'cms_get_template_guide')['templates'][0])->toMatchArray(['path' => 'blog.blade.php', 'view' => 'cms.blog'])
        ->and(cmsTool($super, 'cms_write_template', ['path' => 'blog.blade.php', 'delete' => true]))->toMatchArray(['deleted' => true])
        ->and(Activity::query()->where('subject_type', 'template')->pluck('action')->all())->toBe(['created', 'updated', 'deleted'])
        ->and(Activity::query()->where('action', 'template changed')->count())->toBe(3);
    File::deleteDirectory($views);
    File::deleteDirectory(storage_path('app/sunrice-cms'));
});

it('gives read-only tokens the new reading tools only, and the CMS docs', function () {
    $readOnly = cmsAgent(['cms.entries.view', 'cms.blueprints.manage', 'cms.menus.view'], readOnly: true);
    $tools = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Authorization' => "Bearer {$readOnly}"])->json('result.tools.*.name');
    expect($tools)->toContain('cms_get_listing', 'cms_get_blueprint', 'cms_field_types', 'cms_get_menu', 'cms_seo_audit', 'cms_render_page', 'cms_get_template_guide', 'cms_read_template')
        ->not->toContain('cms_entry_revisions', 'cms_reorder', 'cms_save_listing', 'cms_save_blueprint', 'cms_save_menu', 'cms_delete_submissions', 'cms_manage_entry', 'cms_write_template');
    expect(cmsTool($readOnly, 'cms_get_menu'))->toBe(['menus' => []]);

    $docs = collect(cmsTool($readOnly, 'read_docs')['sections'])->firstWhere('section', 'CMS');
    expect(array_column($docs['pages'], 'page'))->toContain('cms/site', 'cms/ai');
});
