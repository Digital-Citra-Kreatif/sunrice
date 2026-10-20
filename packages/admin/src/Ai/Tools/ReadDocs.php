<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Panel;

#[IsReadOnly]
#[Description('Read the developer documentation of the panel and its plugins (how resources, settings, fields, templates, menus … work). Without "page": the pages, as "section/page" with their titles. With one (e.g. "panel/resources", "cms/site"): its Markdown.')]
final class ReadDocs extends Tool
{
    protected string $name = 'read_docs';

    public function handle(Request $request): Response
    {
        if (! $this->can(Panel::ACCESS)) {
            return $this->denied('read the docs');
        }
        $page = $request->get('page');
        $docs = app(Panel::class)->getDocs();

        if ($page === null || $page === '') {
            $sections = [];
            foreach ($docs as $key => ['title' => $title, 'directory' => $directory]) {
                $pages = [];
                foreach (glob($directory.'/*.md') ?: [] as $file) {
                    $pages[] = ['page' => $key.'/'.basename($file, '.md'), 'title' => self::heading($file)];
                }
                if ($pages !== []) {
                    $sections[] = ['section' => $title, 'pages' => $pages];
                }
            }

            return $this->json(['sections' => $sections]);
        }

        // "section/page": letters, numbers and dashes only, so no other file can be named.
        if (! is_string($page) || preg_match('#^([a-z0-9-]+)/([a-z0-9_-]+)$#', $page, $m) !== 1 || ! isset($docs[$m[1]])) {
            return Response::error('No such page. Call read_docs without "page" for the list.');
        }
        $directory = realpath($docs[$m[1]]['directory']);
        $file = $directory === false ? false : realpath($directory.'/'.$m[2].'.md');
        if ($directory === false || $file === false || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return Response::error('No such page. Call read_docs without "page" for the list.');
        }

        return Response::text((string) file_get_contents($file));
    }

    /** A page's first heading, else its name. */
    private static function heading(string $file): string
    {
        $handle = fopen($file, 'rb');
        $first = $handle === false ? false : fgets($handle);
        if ($handle !== false) {
            fclose($handle);
        }

        return is_string($first) && str_starts_with($first, '#') ? trim(ltrim($first, '# ')) : basename($file, '.md');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('"section/page", e.g. "panel/resources"; leave out for the list.')];
    }
}
