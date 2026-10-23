<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether a password appears in a known data leak, asked of Have I Been
 * Pwned's Pwned Passwords range API by k-anonymity: only the first 5
 * characters of the password's SHA-1 hash leave the server, with padding
 * so the answer's size says nothing either.
 *
 * It fails open: when the service can't be reached or answers with an
 * error, the password isn't refused (a warning is logged, once an hour).
 *
 * @internal
 */
final class LeakedPasswords
{
    public const URL = 'https://api.pwnedpasswords.com/range/';

    public const TIMEOUT_SECONDS = 3;

    public function leaked(string $password): bool
    {
        if ($password === '') {
            return false;
        }
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = Http::withHeaders(['Add-Padding' => 'true'])->timeout(self::TIMEOUT_SECONDS)->get(self::URL.$prefix);
            if (! $response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()}");
            }
            $body = $response->body();
        } catch (Throwable $e) {
            if (Cache::add('sunrice-leaked-passwords-unreachable', true, 3600)) {
                Log::warning('Sunrice couldn\'t check passwords against known data leaks (api.pwnedpasswords.com), so they were accepted unchecked: '.$e->getMessage());
            }

            return false;
        }

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            [$found, $count] = array_pad(explode(':', trim($line), 2), 2, '0');
            // Padding lines have a count of 0.
            if (hash_equals($suffix, strtoupper($found)) && (int) $count > 0) {
                return true;
            }
        }

        return false;
    }
}
