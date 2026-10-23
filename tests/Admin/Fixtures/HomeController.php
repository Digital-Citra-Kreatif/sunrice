<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** An app's own dashboard (Panel::dashboard()). */
final class HomeController
{
    public function __invoke(Request $request): Response
    {
        $name = $request->user()?->getAttribute('name');

        return Inertia::render('App/Home', ['user' => is_string($name) ? $name : null]);
    }
}
