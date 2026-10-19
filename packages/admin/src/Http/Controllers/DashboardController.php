<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Dashboard\DashboardCard;
use Sunrice\Admin\Panel;

/** @internal */
final class DashboardController
{
    public function __invoke(Request $request, Panel $panel): Response
    {
        $user = $request->user();
        if (! $user instanceof Authorizable) {
            abort(403);
        }
        $cards = $panel->dashboardCardsFor($user);

        return Inertia::render('Dashboard', [
            // The frames at once, what's in them once the page shows.
            'cards' => array_map(fn (DashboardCard $card): array => $card->frame($panel->getPath()), $cards),
            'content' => Inertia::defer(function () use ($cards, $user): array {
                $content = [];
                foreach ($cards as $card) {
                    $content[$card->key] = $card->content($user);
                }

                return $content;
            }),
        ]);
    }
}
