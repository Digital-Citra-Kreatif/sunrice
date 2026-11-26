<?php

declare(strict_types=1);

namespace Sunrice\Cms\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Cms\Editor\EditorCards;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Support\Access;

/**
 * Saves a plugin's card in the entry or term editor (Cms::editorCard()).
 * The routes check the user may update the entry or term; the card's
 * own check (EditorCard::can()) is checked here. A card is about the
 * whole entry, like its settings: not for translators, whose "locale"
 * lets them past the route's check for their languages' text. Nor on
 * an entry or term whose collection or taxonomy is in the trash.
 *
 * @internal
 */
final class EditorCardController
{
    public function entry(Request $request, Entry $entry, string $card, EditorCards $cards): RedirectResponse
    {
        abort_if($entry->collection->trashed(), 404);
        $user = $request->user();
        abort_unless(Access::entries($user instanceof Authorizable ? $user : null, 'update', $entry->collection, $entry), 403);

        return $this->save($request, $entry, $card, $cards);
    }

    public function term(Request $request, Term $term, string $card, EditorCards $cards): RedirectResponse
    {
        abort_if($term->taxonomy->trashed(), 404);

        return $this->save($request, $term, $card, $cards);
    }

    private function save(Request $request, Entry|Term $record, string $key, EditorCards $cards): RedirectResponse
    {
        $user = $request->user();
        $user = $user instanceof Authorizable ? $user : null;
        $card = $cards->find($key, $record, $user);
        abort_if($card === null || $user === null, 404);
        $input = $request->input('values', []);
        $card->store($record, is_array($input) ? $input : [], $user);

        return back()->with('success', __(':card saved.', ['card' => $card->title()]));
    }
}
