<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\RichTextLinkAttributes;

/**
 * The rich text editor's link menu: searching what links can point at
 * (Panel::richTextLinkSource()) and naming a saved link's item. Behind
 * sign-in and Panel::ACCESS like every panel route; each source lists only
 * what the signed-in user may view.
 *
 * @internal
 */
final class RichTextLinkController
{
    public function __construct(private readonly Panel $panel) {}

    /**
     * "q": what was typed; "source": one source's key, else all of them.
     * Each source's items, under its label: {groups: [{key, label, items}]}.
     */
    public function search(Request $request): JsonResponse
    {
        /** @var array{q?: string|null, source?: string|null} $input */
        $input = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'source' => ['nullable', 'string', 'max:64']]);
        $query = trim($input['q'] ?? '');
        $only = $input['source'] ?? null;
        $user = $request->user();
        $groups = [];
        if (mb_strlen($query) >= 2 && $user instanceof Authorizable) {
            foreach ($this->panel->getRichTextLinkSources() as $key => $source) {
                if ($only !== null && $only !== $key) {
                    continue;
                }
                $items = $source->search($query, $user);
                if ($items !== []) {
                    $groups[] = ['key' => $source->key, 'label' => $source->label(), 'items' => $items];
                }
            }
        }

        return response()->json(['groups' => $groups]);
    }

    /** A saved link's item ("entry:12"), with its source's label, or null when it's gone or not the user's to see. */
    public function show(Request $request, string $link): JsonResponse
    {
        $user = $request->user();
        $item = null;
        if (RichTextLinkAttributes::link($link) !== null && $user instanceof Authorizable) {
            [$key, $id] = explode(':', $link, 2);
            $source = $this->panel->getRichTextLinkSources()[$key] ?? null;
            $found = $source?->find([(int) $id], $user)[0] ?? null;
            if ($source !== null && $found !== null && $found['id'] === (int) $id) {
                $item = $found + ['source' => $source->label()];
            }
        }

        return response()->json(['item' => $item]);
    }
}
