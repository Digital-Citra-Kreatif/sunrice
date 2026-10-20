<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Sunrice\Admin\Ai\DeveloperTools;

#[IsReadOnly]
#[Description('List the app\'s Blade views (templates) under resources/views, by name as Laravel writes them ("cms.article" is resources/views/cms/article.blade.php). "prefix" narrows it to a folder, like "cms". Read one with read_view.')]
final class ListViews extends DeveloperTool
{
    protected string $name = 'list_views';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.views.view')) {
            return $this->denied('read views');
        }
        $prefix = DeveloperTools::viewName($request->get('prefix'));
        $root = DeveloperTools::viewsPath();
        $views = [];
        if (is_dir($root)) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $name = DeveloperTools::viewName($relative);
                if (! $file->isFile() || ! str_ends_with($relative, '.blade.php') || $name === null) {
                    continue;
                }
                if ($prefix !== null && $name !== $prefix && ! str_starts_with($name, "{$prefix}.")) {
                    continue;
                }
                $views[] = ['name' => $name, 'size' => $file->getSize(), 'modified' => date(DATE_ATOM, $file->getMTime())];
            }
        }
        usort($views, fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $this->json(['views' => array_slice($views, 0, 500), 'total' => count($views)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prefix' => $schema->string()->description('A folder: "cms", "layouts", "vendor.sunrice-cms".'),
        ];
    }
}
