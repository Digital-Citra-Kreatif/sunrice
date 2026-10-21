<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Site\Page;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pages and neighbourhood guides that open with a full-bleed photo get "has-hero": the header then sits
        // transparent over the photo (resources/css/app.css). Reads the stored sections' block types only, so
        // it costs no query and no hydration.
        Cms::bodyClassUsing(static function (Page $page): array {
            $entry = $page->entry;
            if ($entry === null) {
                return [];
            }
            $data = $page->preview ? $entry->draft_data : $entry->data;
            if (! is_array($data)) {
                return [];
            }

            return match ($page->collection?->handle) {
                'pages' => (($data['sections'][0]['type'] ?? null) === 'hero') ? ['has-hero'] : [],
                'neighbourhoods' => ['has-hero'],
                default => [],
            };
        });
    }
}
