<?php

declare(strict_types=1);

namespace Sunrice\Admin\Locks;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Edit locks, WordPress style: an open editor holds the lock of what it
 * edits (an item, perhaps in one language) and renews it every few
 * seconds; a lock nobody renews expires on its own. Someone else may take
 * it over, and the previous editor learns it on its next renewal.
 *
 * Keys come from key(): "cms.entry:12:en". Locks live in the cache
 * (sunrice-admin.locks.store): short-lived, and fine on several servers
 * as long as they share the store.
 *
 * @api
 */
final class EditLocks
{
    /** Seconds a lock lives without being renewed. */
    public const TTL = 120;

    /**
     * Takes the lock when it's free (or already the person's own) and renews it.
     *
     * @param  string  $editor  the editing tab: one person may have an item open twice
     * @return array{status: 'ok'}|array{status: 'locked', by: string, since: int}|array{status: 'taken', by: string}
     */
    public function acquire(string $key, string $editor, int|string $userId, string $userName): array
    {
        $taken = $this->store()->pull($this->takenKey($key, $editor));
        if (is_array($taken)) {
            // Told once: opening the item again later takes the lock as usual.
            return ['status' => 'taken', 'by' => is_string($taken['by'] ?? null) ? $taken['by'] : ''];
        }

        $lock = ['editor' => $editor, 'user_id' => $userId, 'name' => $userName, 'since' => time()];
        if ($this->store()->add($this->lockKey($key), $lock, self::TTL)) {
            return ['status' => 'ok'];
        }

        $current = $this->current($key);
        // Their own tab, another tab of theirs, or a closed one not expired yet:
        // nobody is locked out by themselves.
        if ($current === null || $current['editor'] === $editor || (string) $current['user_id'] === (string) $userId) {
            $lock['since'] = $current['since'] ?? time();
            $this->store()->put($this->lockKey($key), $lock, self::TTL);

            return ['status' => 'ok'];
        }

        return ['status' => 'locked', 'by' => $current['name'], 'since' => $current['since']];
    }

    /** Takes the lock from whoever holds it; they're told on their next renewal. */
    public function takeOver(string $key, string $editor, int|string $userId, string $userName): void
    {
        $current = $this->current($key);
        if ($current !== null && $current['editor'] !== $editor) {
            $this->store()->put($this->takenKey($key, $current['editor']), ['by' => $userName], 600);
        }
        $this->store()->put($this->lockKey($key), ['editor' => $editor, 'user_id' => $userId, 'name' => $userName, 'since' => time()], self::TTL);
    }

    /**
     * Frees the lock when this editing tab holds it (and, given $userId,
     * when it's that person's).
     */
    public function release(string $key, string $editor, int|string|null $userId = null): void
    {
        $current = $this->current($key);
        if ($current !== null && $current['editor'] === $editor && ($userId === null || (string) $current['user_id'] === (string) $userId)) {
            $this->store()->forget($this->lockKey($key));
        }
    }

    /** @return array{editor: string, user_id: int|string, name: string, since: int}|null */
    public function current(string $key): ?array
    {
        $lock = $this->store()->get($this->lockKey($key));
        if (! is_array($lock) || ! is_string($lock['editor'] ?? null) || ! is_string($lock['name'] ?? null) || ! is_int($lock['since'] ?? null)) {
            return null;
        }
        $userId = $lock['user_id'] ?? 0;

        return ['editor' => $lock['editor'], 'user_id' => is_int($userId) || is_string($userId) ? $userId : 0, 'name' => $lock['name'], 'since' => $lock['since']];
    }

    /** The lock key of an item: its type, id and language ('' for none). */
    public static function key(string $type, string $id, string $locale = ''): string
    {
        return $type.':'.$id.($locale === '' ? '' : ':'.$locale);
    }

    private function lockKey(string $key): string
    {
        return 'sunrice:edit-lock:'.$key;
    }

    private function takenKey(string $key, string $editor): string
    {
        return 'sunrice:edit-lock-taken:'.$key.':'.$editor;
    }

    private function store(): Repository
    {
        $store = config('sunrice-admin.locks.store');

        return Cache::store(is_string($store) ? $store : null);
    }
}
