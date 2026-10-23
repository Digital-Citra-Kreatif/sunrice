<?php

declare(strict_types=1);

// The Blog starter's own words (lang/en/site.php, __('site.…')): the same keys in every language's file.
// The CMS's site words (comments, forms) are its own: override them in lang/vendor/sunrice-cms/<locale>/site.php.
return [
    'skip' => 'Skip to content',
    'menus' => [
        'main' => 'Main',
        'footer' => 'Footer',
    ],
    'empty' => 'Nothing here yet.',
    'search' => [
        'title' => 'Search',
        'label' => 'Search the site',
        'button' => 'Search',
        'results' => ':count result for “:query”|:count results for “:query”',
    ],
    'pagination' => [
        'label' => 'Pages',
        'previous' => 'Previous',
        'next' => 'Next',
        'page' => 'Page :page of :pages',
    ],
    'errors' => [
        'not_found' => 'Page not found',
        'not_found_text' => 'The page you asked for isn\'t here. It may have moved, or the address may be mistyped.',
        'failed' => 'Something went wrong',
        'failed_text' => 'Sorry, the page couldn\'t be shown. Please try again in a moment.',
        'home' => 'Go to the home page',
    ],
];
