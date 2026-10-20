<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * The workbench app (`composer serve`). Sign in at
 * /_workbench/login/admin@example.com (a super admin, password "password").
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->followServeAddress();
        $this->app->register(AdminPanelProvider::class);
    }

    /**
     * Under `testbench serve` the app is set up before the request arrives, so
     * URLs made then (the CMS bundle's, through asset()) and the public
     * disk's (media files) would use APP_URL from testbench.yaml. Use the address the server answers on instead, so
     * `testbench serve --port=8001` or any other host and port just works.
     */
    private function followServeAddress(): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        if (PHP_SAPI !== 'cli-server' || ! is_string($host) || $host === '') {
            return;
        }
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
        $root = ($https ? 'https' : 'http').'://'.$host;

        config(['app.url' => $root, 'filesystems.disks.public.url' => $root.'/storage']);
        URL::forceRootUrl($root);
    }
}
