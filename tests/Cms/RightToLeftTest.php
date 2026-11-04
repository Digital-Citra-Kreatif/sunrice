<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Comments\PostComment;
use Sunrice\Cms\Actions\Comments\SetCommentStatus;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Mail\CommentPosted;
use Sunrice\Cms\Mail\CommentReplied;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Comment;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\PageLanguage;
use Sunrice\Cms\Support\Locales;
use Sunrice\Cms\View\Components\LanguageSwitcher;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function rtlPost(Collection $collection, string $title, string $slug, ?string $arabic = null, ?string $arabicSlug = null): EntryTranslation
{
    $post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title, 'slug' => $slug, 'data' => ['body' => '<p>Text</p>']]));
    if ($arabic !== null) {
        $translation = app(CreateTranslation::class)->handle($post->entry, 'ar');
        app(SaveDraft::class)->handle($translation, ['title' => $arabic, 'slug' => $arabicSlug ?? $slug]);
        app(PublishEntry::class)->handle($translation->refresh());
    }

    return $post;
}

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'ar']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'index_route' => '/tags']);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'comments' => true]);
    $this->tags->collections()->attach($this->blog);
});

it('says which languages are written right to left, by their primary subtag', function () {
    expect(Locales::dir('ar'))->toBe('rtl')
        ->and(Locales::dir('ar_EG'))->toBe('rtl')
        ->and(Locales::dir('ar-SA'))->toBe('rtl')
        ->and(Locales::dir('AR'))->toBe('rtl')
        ->and(Locales::dir('he'))->toBe('rtl')
        ->and(Locales::dir('fa_IR'))->toBe('rtl')
        ->and(Locales::dir('ur'))->toBe('rtl')
        ->and(Locales::dir('en'))->toBe('ltr')
        ->and(Locales::dir('pt_BR'))->toBe('ltr')
        ->and(Locales::dir('id'))->toBe('ltr')
        // "arn" (Mapuche) isn't Arabic: the whole subtag counts, not its start.
        ->and(Locales::dir('arn'))->toBe('ltr')
        ->and(Locales::dir(''))->toBe('ltr')
        ->and(Locales::rtl('he_IL'))->toBeTrue()
        ->and(Locales::rtl('de'))->toBeFalse();

    // Null: the app's locale.
    app()->setLocale('ar');
    expect(Locales::dir())->toBe('rtl')->and(Content::dir())->toBe('rtl')->and(cms_dir())->toBe('rtl')->and(cms_dir('en'))->toBe('ltr');
    app()->setLocale('en');
    expect(cms_dir())->toBe('ltr');
});

it('takes the right-to-left languages from config', function () {
    config(['sunrice-cms.rtl_locales' => ['xx', 'HE']]);

    expect(Locales::dir('xx_YY'))->toBe('rtl')
        ->and(Locales::dir('he'))->toBe('rtl')
        ->and(Locales::dir('ar'))->toBe('ltr');

    config(['sunrice-cms.rtl_locales' => []]);
    expect(Locales::dir('ar'))->toBe('ltr');
    config(['sunrice-cms.rtl_locales' => 'ar']); // not a list: none
    expect(Locales::dir('ar'))->toBe('ltr');
});

it('gives the page its direction, and the content its own only when it differs', function () {
    $page = new Page(Page::ENTRY, 'ar', 'sunrice-cms::entry');
    expect($page->dir())->toBe('rtl')->and($page->contentDir())->toBe('rtl')->and((string) $page->dirAttribute())->toBe('');

    $english = rtlPost($this->blog, 'Hello', 'hello');
    $fallback = new Page(Page::ENTRY, 'ar', 'sunrice-cms::entry', $english, fallback: true);
    expect($fallback->dir())->toBe('rtl')->and($fallback->contentDir())->toBe('ltr')
        ->and((string) $fallback->dirAttribute())->toBe(' dir="ltr"')
        ->and((string) $fallback->langAttribute())->toBe(' lang="en"');

    // Another language of the same direction: lang only.
    config(['sunrice-cms.locales' => ['en', 'ar', 'id']]);
    $indonesian = new Page(Page::ENTRY, 'id', 'sunrice-cms::entry', $english, fallback: true);
    expect((string) $indonesian->dirAttribute())->toBe('')->and((string) $indonesian->langAttribute())->toBe(' lang="en"');
});

it('puts dir on the html of each of the CMS\'s own pages, rtl in Arabic and ltr in English', function () {
    $news = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);
    app(SaveTerm::class)->handle($this->tags, ['title' => 'أخبار', 'slug' => 'akhbar', 'locale' => 'ar'], $news);
    $post = rtlPost($this->blog, 'Hello', 'hello', 'مرحبا', 'marhaba');
    $post->entry->terms()->sync([$news->id]);
    auth()->logout();

    foreach (['/blog/hello', '/blog', '/tags/news', '/tags', '/search?q=hello'] as $path) {
        get($path)->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }
    foreach (['/ar/blog/marhaba', '/ar/blog', '/ar/tags/akhbar', '/ar/tags', '/ar/search?q=hello'] as $path) {
        get($path)->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
    }
    expect(get('/ar/blog/marhaba')->getContent())->toContain('<article><h1>مرحبا</h1>');

    $question = app(PostComment::class)->handle($post->entry, ['body' => 'سؤال', 'author_name' => 'Rina', 'author_email' => 'rina@example.com'], null, 'ar', '10.0.0.1');
    get(URL::signedRoute('sunrice.cms.comments.unsubscribe', ['comment' => $question->id]))->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
});

it('marks English content on an Arabic page ltr, and Arabic content on an English page rtl', function () {
    rtlPost($this->blog, 'Hello', 'hello');
    $news = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);
    auth()->logout();

    $page = get('/ar/blog/hello')->assertOk()->assertViewHas('fallback', true)->assertSee('<html lang="ar" dir="rtl">', false);
    expect($page->getContent())->toContain('<article lang="en" dir="ltr"><h1>Hello</h1>');
    expect(get('/ar/tags/news')->assertOk()->getContent())->toContain('<article lang="en" dir="ltr"><h1>News</h1>');
    expect(get('/blog/hello')->getContent())->toContain('<article><h1>Hello</h1>');

    // The main language written right to left: its content on an English page is rtl.
    config(['sunrice-cms.locales' => ['ar', 'en']]);
    $arabic = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'مرحبا', 'slug' => 'marhaba']));
    expect($arabic->locale)->toBe('ar');
    $page = get('/en/blog/marhaba')->assertOk()->assertViewHas('fallback', true)->assertSee('<html lang="en" dir="ltr">', false);
    expect($page->getContent())->toContain('<article lang="ar" dir="rtl"><h1>مرحبا</h1>');
});

it('gives each language of the switcher its direction, and dir on links in the other one', function () {
    rtlPost($this->blog, 'Hello', 'hello', 'مرحبا', 'marhaba');
    auth()->logout();
    get('/blog/hello');
    $languages = LanguageSwitcher::languagesOf(Content::page());
    expect(array_map(fn (PageLanguage $language): string => $language->dir, $languages))->toBe(['ltr', 'rtl']);

    $html = Blade::render('<x-sunrice-cms::language-switcher />');
    expect($html)->toContain('<span aria-current="true" lang="en">English</span>')
        ->toContain('hreflang="ar" lang="ar" dir="rtl">');

    get('/ar/blog/marhaba');
    $html = Blade::render('<x-sunrice-cms::language-switcher fallback-note />');
    expect($html)->toContain('aria-current="true" lang="ar">')
        ->toContain('hreflang="en" lang="en" dir="ltr">')
        ->not->toContain('lang="ar" dir=');
});

it('gives the reply email the direction of the language it\'s sent in', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $post = rtlPost($this->blog, 'Hello', 'hello', 'مرحبا', 'marhaba');
    $question = app(PostComment::class)->handle($post->entry, ['body' => 'سؤال', 'author_name' => 'Rina', 'author_email' => 'rina@example.com'], null, 'ar', '10.0.0.1');
    app(SetCommentStatus::class)->handle($question, Comment::APPROVED);
    $answer = app(PostComment::class)->handle($post->entry, ['body' => 'جواب', 'author_name' => 'Budi', 'author_email' => 'budi@example.com', 'parent_id' => $question->id], null, 'ar', '10.0.0.2');

    $html = (new CommentReplied($answer))->render();
    expect($html)->toContain('<html lang="ar" dir="rtl">')->toContain('border-right: 3px solid #ddd;');

    // The moderators' email is written in English, with the comment following its own text.
    $html = (new CommentPosted($question))->render();
    expect($html)->toContain('<html lang="en" dir="ltr">')->toContain('<blockquote dir="auto"');
});

it('writes the starter templates\' and the default pages\' CSS with logical properties only', function () {
    $root = dirname(__DIR__, 2);
    $physical = '/(?:margin|padding|border)-(?:left|right)\s*:|(?:float|text-align|clear)\s*:\s*(?:left|right)\b|(?<![\w-])(?:left|right)\s*:\s*(?!auto)[\d-]/';
    foreach (["{$root}/packages/cms/stubs/public/site.css", "{$root}/packages/starter-kit/starters/blog/public/cms/site.css"] as $file) {
        $css = (string) file_get_contents($file);
        expect(preg_match_all($physical, $css, $found))->toBe(0, "{$file}: ".implode(', ', $found[0]))
            ->and($css)->toContain('.sunrice-img-left { float: inline-start;')
            ->toContain('.sunrice-img-right { float: inline-end;');
    }
    foreach (['entry', 'listing', 'term', 'taxonomy-index', 'search'] as $view) {
        $css = (string) file_get_contents("{$root}/packages/cms/resources/views/{$view}.blade.php");
        expect(preg_match_all($physical, $css, $found))->toBe(0, "{$view}: ".implode(', ', $found[0]));
    }
});
