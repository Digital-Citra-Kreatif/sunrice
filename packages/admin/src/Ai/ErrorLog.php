<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

/**
 * Reads the app's log files (DeveloperTools::logsPath()) for AI agents:
 * the newest entries of Laravel's format ("[2026-01-01 12:00:00]
 * production.ERROR: …"), at or above a level, with obvious secrets
 * (bearer tokens, AI tokens, passwords) blanked out. Only the end of a
 * file is read, so a big log stays cheap.
 *
 * @internal
 */
final class ErrorLog
{
    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    /** How much of the end of a file is read (bytes). */
    public const TAIL = 1024 * 1024;

    /** @return list<array{name: string, size: int, modified: string}> newest first */
    public function files(): array
    {
        $files = [];
        foreach (glob(DeveloperTools::logsPath().'/*.log') ?: [] as $file) {
            $files[] = ['name' => basename($file), 'size' => (int) filesize($file), 'modified' => date(DATE_ATOM, (int) filemtime($file))];
        }
        usort($files, fn (array $a, array $b): int => [$b['modified'], $b['name']] <=> [$a['modified'], $a['name']]);

        return $files;
    }

    /**
     * @return list<array{at: string, level: string, message: string, details: string}>|null newest first; null when there's no such file
     */
    public function entries(string $file, string $level = 'error', ?string $search = null, int $limit = 20): ?array
    {
        if (preg_match('/^[A-Za-z0-9._-]+\.log$/', $file) !== 1 || ! is_file($path = DeveloperTools::logsPath().'/'.$file)) {
            return null;
        }
        $minimum = array_search(strtolower($level), self::LEVELS, true);
        $minimum = $minimum === false ? 4 : $minimum;
        $text = $this->tail($path);
        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T][^\]]*\] [\w-]+\.[A-Z]+: )/m', $text) ?: [];

        $entries = [];
        foreach (array_reverse($parts) as $part) {
            if (preg_match('/^\[([^\]]+)\] [\w-]+\.([A-Z]+): (.*)$/s', $part, $m) !== 1) {
                continue;
            }
            $rank = array_search(strtolower($m[2]), self::LEVELS, true);
            if ($rank === false || $rank < $minimum || ($search !== null && $search !== '' && stripos($part, $search) === false)) {
                continue;
            }
            [$message, $details] = array_pad(explode("\n", self::redact(trim($m[3])), 2), 2, '');
            $entries[] = [
                'at' => $m[1],
                'level' => strtolower($m[2]),
                'message' => mb_strimwidth($message, 0, 2000, '…'),
                'details' => mb_strimwidth(trim($details), 0, 4000, '…'),
            ];
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /** Blank out what looks like a secret. */
    public static function redact(string $text): string
    {
        return preg_replace([
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
            '/\bsra_[A-Za-z0-9]+/',
            '/(["\']?(?:password|passwd|secret|token|api[_-]?key)["\']?\s*(?:=>|[:=])\s*)(["\']?)[^\s"\',)]+/i',
        ], ['$1 [hidden]', 'sra_[hidden]', '$1$2[hidden]'], $text) ?? $text;
    }

    private function tail(string $path): string
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        if ($size > self::TAIL) {
            fseek($handle, -self::TAIL, SEEK_END);
        }
        $text = stream_get_contents($handle);
        fclose($handle);

        return is_string($text) ? $text : '';
    }
}
