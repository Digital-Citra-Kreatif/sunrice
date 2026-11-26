<?php

declare(strict_types=1);

namespace Sunrice\Tests;

use RuntimeException;

use function Orchestra\Testbench\default_skeleton_path;

/**
 * Under `pest --parallel`, each worker's own copy of Testbench's app
 * skeleton as the app's base path. Tests publish templates, translations
 * and routes into the app (resource_path(), public_path(), base_path())
 * and delete them after; in the one shared skeleton, another worker's
 * test meanwhile renders those half-there files ("View [cms.partials.fields]
 * not found"). A run in one process keeps the shared skeleton.
 */
trait WorkerSkeleton
{
    public static function applicationBasePath()
    {
        return self::workerSkeleton() ?? parent::applicationBasePath();
    }

    private static function workerSkeleton(): ?string
    {
        static $path = null;
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN');
        if (! is_scalar($token) || (string) $token === '') {
            return null;
        }
        if (is_string($path)) {
            return $path;
        }
        $source = default_skeleton_path();
        if (! is_string($source)) {
            return null;
        }
        $copy = dirname(__DIR__).'/.phpunit.cache/skeleton-'.$token;
        // Made again on every run (a Testbench update changes the skeleton); `cp -a` keeps
        // the skeleton's links (vendor, public/storage) as links instead of copying what they point to.
        exec('rm -rf '.escapeshellarg($copy).' && mkdir -p '.escapeshellarg(dirname($copy))
            .' && cp -a '.escapeshellarg($source).' '.escapeshellarg($copy), $output, $status);
        if ($status !== 0) {
            throw new RuntimeException("Couldn't copy Testbench's skeleton to {$copy}.");
        }
        // Compiled views from earlier runs: not needed, and they'd be stale.
        exec('rm -rf '.escapeshellarg($copy.'/storage/framework/views').'/*.php');

        return $path = $copy;
    }
}
