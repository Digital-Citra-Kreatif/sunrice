<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The guide the panel and the CMS ship, in both languages: every page
 * renders, and every link to another guide or docs page, with its anchor,
 * leads somewhere.
 */

it('ships every guide page in English and Indonesian, with links that lead somewhere', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view', 'sunrice.docs.view'));
    $root = dirname(__DIR__, 2);

    $pages = [];
    foreach (['panel' => 'packages/admin/guide', 'website' => 'packages/cms/guide'] as $section => $directory) {
        $english = array_map(basename(...), glob("{$root}/{$directory}/en/*.md") ?: []);
        expect($english)->not->toBeEmpty()
            ->and(array_map(basename(...), glob("{$root}/{$directory}/id/*.md") ?: []))->toBe($english);
        foreach ($english as $file) {
            $page = $section.'/'.preg_replace('/^\d+-/', '', basename($file, '.md'));
            foreach (['en', 'id'] as $language) {
                $markdown = (string) file_get_contents("{$root}/{$directory}/{$language}/{$file}");
                $props = [];
                get("/admin/guide/{$page}?lang={$language}")->assertOk()->assertInertia(function (Assert $inertia) use (&$props) {
                    $props = $inertia->toArray()['props'];
                });
                expect($props['shownIn'])->toBeNull()
                    ->and($props['title'])->not->toContain('{panel}')
                    ->and($props['html'])->not->toContain('{panel}');
                // Every guide: and docs: link became a link.
                preg_match_all('/\]\((guide|docs):/', $markdown, $written);
                preg_match_all('#href="/admin/(guide|docs)/#', $props['html'], $rendered);
                expect(count($rendered[0]))->toBe(count($written[0]), "{$language}/{$file}");
                $pages["{$page}|{$language}"] = $props;
                preg_match_all('#href="/admin/(guide|docs)/([a-z0-9-]+/[a-z0-9_-]+)(?:\#([^"]+))?"#', $props['html'], $links, PREG_SET_ORDER);
                foreach ($links as $link) {
                    $pages['links'][] = [$link[1], $link[2], $link[3] ?? '', $language, "{$language}/{$file}"];
                }
                preg_match_all('#href="\#([^"]+)"#', $props['html'], $anchors);
                foreach ($anchors[1] as $anchor) {
                    expect(in_array($anchor, array_column($props['headings'], 'id'), true))->toBeTrue("{$language}/{$file} links to #{$anchor}");
                }
            }
        }
    }

    // Anchors on other pages exist there (in the reader's language for the guide).
    foreach ($pages['links'] ?? [] as [$kind, $target, $anchor, $language, $from]) {
        $headings = [];
        get("/admin/{$kind}/{$target}".($kind === 'guide' ? "?lang={$language}" : ''))->assertOk()->assertInertia(function (Assert $inertia) use (&$headings) {
            $headings = $inertia->toArray()['props']['headings'];
        });
        if ($anchor !== '') {
            expect(in_array($anchor, array_column($headings, 'id'), true))->toBeTrue("{$from} links to {$kind}/{$target}#{$anchor}");
        }
    }
});
