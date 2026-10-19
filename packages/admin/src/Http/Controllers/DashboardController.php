<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** @internal */
final class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
