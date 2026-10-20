<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Locks\EditLocks;
use Sunrice\Admin\Locks\KeptEdit;
use Sunrice\Admin\Panel;

/**
 * The JSON behind editors' edit locks (useEditLock): renew, take over,
 * release, keep unsaved changes when taken over, and load or discard kept
 * changes. Each item is checked with its type's lockable() callback.
 *
 * @internal
 */
final class EditLocksController
{
    public function __construct(private readonly EditLocks $locks, private readonly Panel $panel) {}

    /** Takes or renews the lock; also lists the changes kept for the item. */
    public function renew(Request $request, string $type, string $id): JsonResponse
    {
        [$key, $locale] = $this->item($request, $type, $id);
        $state = $this->locks->acquire($key, self::editor($request), self::userId($request), self::userName($request));

        return response()->json($state + ['kept' => self::kept($type, $id, $locale)]);
    }

    public function take(Request $request, string $type, string $id): JsonResponse
    {
        [$key] = $this->item($request, $type, $id);
        $this->locks->takeOver($key, self::editor($request), self::userId($request), self::userName($request));

        return response()->json(['status' => 'ok']);
    }

    public function release(Request $request, string $type, string $id): JsonResponse
    {
        [$key] = $this->item($request, $type, $id);
        $this->locks->release($key, self::editor($request));

        return response()->json(['status' => 'released']);
    }

    /** The unsaved changes of an editor that was taken over. */
    public function keep(Request $request, string $type, string $id): JsonResponse
    {
        [, $locale] = $this->item($request, $type, $id);
        $request->validate(['content' => ['required', 'array'], 'taken_by' => ['nullable', 'string', 'max:255']]);
        if (strlen((string) json_encode($request->input('content'))) > 1_000_000) {
            abort(413);
        }
        /** @var array<string, mixed> $content */
        $content = $request->input('content');
        $takenBy = $request->input('taken_by');
        $kept = KeptEdit::query()->create([
            'type' => $type,
            'item_id' => $id,
            'locale' => $locale,
            'user_id' => (string) self::userId($request),
            'user_name' => self::userName($request),
            'taken_by' => is_string($takenBy) ? $takenBy : null,
            'content' => $content,
        ]);
        // At most a few per person and item: the older ones go.
        $older = KeptEdit::query()->where('type', $type)->where('item_id', $id)->where('user_id', (string) self::userId($request))
            ->orderByDesc('id')->skip(KeptEdit::KEEP_PER_EDITOR)->take(100)->pluck('id');
        if ($older->isNotEmpty()) {
            KeptEdit::query()->whereKey($older)->delete();
        }

        return response()->json(['kept' => $kept->id]);
    }

    public function show(Request $request, KeptEdit $kept): JsonResponse
    {
        $request->merge(['locale' => $kept->locale]);
        $this->item($request, $kept->type, $kept->item_id);

        return response()->json(['content' => $kept->content]);
    }

    public function destroy(Request $request, KeptEdit $kept): JsonResponse
    {
        $request->merge(['locale' => $kept->locale]);
        $this->item($request, $kept->type, $kept->item_id);
        $kept->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * The lock key and language of an item the user may edit; 404 for an
     * unknown type, 403 when they may not edit it.
     *
     * @return array{0: string, 1: string}
     */
    private function item(Request $request, string $type, string $id): array
    {
        $mayEdit = $this->panel->getLockables()[$type] ?? null;
        abort_if($mayEdit === null || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1, 404);
        $locale = $request->input('locale');
        $locale = is_string($locale) && preg_match('/^[A-Za-z_-]{0,16}$/', $locale) === 1 ? $locale : '';
        $user = $request->user();
        abort_unless($user instanceof Authorizable && $mayEdit($id, $locale, $user), 403);

        return [EditLocks::key($type, $id, $locale), $locale];
    }

    private static function userId(Request $request): int|string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($id) || is_string($id), 403);

        return $id;
    }

    private static function userName(Request $request): string
    {
        $user = $request->user();
        $name = $user instanceof Model ? $user->getAttribute('name') : null;

        return is_string($name) && $name !== '' ? $name : 'Someone';
    }

    /** The editing tab: one person may have the same item open twice. */
    private static function editor(Request $request): string
    {
        $request->validate(['editor' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/']]);

        return $request->string('editor')->toString();
    }

    /** @return list<array{id: int, by: string|null, taken_by: string|null, at: string|null}> */
    private static function kept(string $type, string $id, string $locale): array
    {
        return array_values(KeptEdit::query()->where('type', $type)->where('item_id', $id)->where('locale', $locale)
            ->where('created_at', '>=', now()->subDays(KeptEdit::KEEP_DAYS))->latest('id')->limit(10)->get()
            ->map(fn (KeptEdit $k): array => ['id' => $k->id, 'by' => $k->user_name, 'taken_by' => $k->taken_by, 'at' => $k->created_at?->toIso8601String()])
            ->all());
    }
}
