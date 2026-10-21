<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Support\AppUrl;
use Sunrice\Cms\Settings\SiteSettings;

// The site's pages are served by Sunrice CMS (its route is the app's fallback): entries, listing and term pages,
// search, sitemap, forms and comments. Routes added here win over the CMS's, so keep this file for the app's
// own endpoints only.

// robots.txt with the sitemap's full address (a static file can't know the host), on APP_URL rather than the
// request's Host header, which whoever asks chooses; a site hidden from search engines (Settings → Site, or
// SUNRICE_NOINDEX on staging) asks them to stay out altogether.
Route::get('robots.txt', function (SiteSettings $site) {
    $lines = $site->hidden()
        ? ['User-agent: *', 'Disallow: /']
        : ['User-agent: *', 'Disallow: /admin', '', 'Sitemap: '.AppUrl::route('sunrice.cms.sitemap')];

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');
