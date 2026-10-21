<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\get;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'comments' => true, 'listing_route' => '/blog', 'per_page' => 1]);
    foreach (['Kopi', 'Teh'] as $title) {
        $post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => $title, 'data' => ['intro' => $title]]));
        $id = app(CreateTranslation::class)->handle($post->entry, 'id');
        app(SaveDraft::class)->handle($id, ['slug' => strtolower($title)]);
        app(PublishEntry::class)->handle($id->fresh() ?? $id);
    }
});

it('shows the site\'s own words in the page\'s language', function () {
    get('/id/blog')->assertOk()->assertSee('Lebih lama')->assertDontSee('Older');
    get('/blog')->assertOk()->assertSee('Older');

    get('/id/blog/kopi')->assertOk()->assertSee('Tinggalkan komentar')->assertSee('Kirim komentar')->assertSee('Email (tidak ditampilkan)');
    get('/blog/kopi')->assertOk()->assertSee('Leave a comment')->assertSee('Post comment');

    commitForFullText();
    get('/id/search?q=kopi')->assertOk()->assertSee('<h1>Cari</h1>', false)->assertSee('1 hasil untuk “kopi”');
    get('/search?q=kopi')->assertOk()->assertSee('1 result for “kopi”');
});

it('brings Indonesian for Laravel\'s own lines the app has none of', function () {
    app()->setLocale('id');

    expect(__('pagination.next'))->toBe('Berikutnya &raquo;')
        ->and(__('auth.failed'))->toBe('Email atau kata sandi salah.')
        ->and(__('passwords.user'))->toBe('Tidak ada pengguna dengan alamat email itu.')
        ->and(__('Showing'))->toBe('Menampilkan')
        ->and(Validator::make(['email' => ''], ['email' => 'required'])->errors()->first('email'))->toBe('email wajib diisi.')
        ->and(__('sunrice-cms::site.comments.reply'))->toBe('Balas');

    app()->setLocale('en');
    expect(__('pagination.next'))->toBe('Next &raquo;')
        ->and(__('sunrice-cms::site.comments.reply'))->toBe('Reply');
});
