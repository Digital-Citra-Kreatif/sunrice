<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\Tool;

/**
 * The site's recent errors from the Laravel log: the last 2 MB of the
 * newest log files, parsed, grouped and with secrets masked.
 *
 * @phpstan-type LogEntry array{at: string, environment: string, level: string, message: string, exception?: string, at_file?: string, trace?: list<string>}
 */
#[IsReadOnly]
#[Description('Read the site\'s recent errors from the Laravel log, newest first. Identical errors are grouped, with a count and when they first and last happened; each has the level, message, exception class, the file and line, and the top of the stack trace (the site\'s own files first). Passwords, tokens and keys are masked. Filter with "level" (the lowest to include, default error), "since_hours" and "search". Use it when the user reports something broken; explain the likely cause and the fix, and ask before changing anything.')]
final class ReadErrorLog extends Tool
{
    protected string $name = 'read_error_log';

    /** Monolog's levels, most severe first. */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** How much of the end of each log file is read. */
    private const TAIL_BYTES = 2 * 1024 * 1024;

    private const MAX_FILES = 3;

    private const FRAMES = 8;

    public function handle(Request $request): Response
    {
        if (config('sunrice-admin.ai.error_log', true) === false) {
            return Response::error('Reading the error log is turned off on this site (sunrice-admin.ai.error_log).');
        }
        if (! $this->can('sunrice.ai.error-log')) {
            return $this->denied('read the error log');
        }
        /** @var array{level?: string|null, since_hours?: int|null, search?: string|null, limit?: int|null} $args */
        $args = $request->validate([
            'level' => ['nullable', 'string', 'in:'.implode(',', self::LEVELS)],
            'since_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $maxLevel = (int) array_search($args['level'] ?? 'error', self::LEVELS, true);
        $since = isset($args['since_hours']) ? now()->subHours($args['since_hours'])->format('Y-m-d H:i:s') : null;
        $search = isset($args['search']) && $args['search'] !== '' ? mb_strtolower($args['search']) : null;

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $files = self::files();
        foreach ($files as $file) {
            foreach (self::entries($file) as $entry) {
                $rank = array_search($entry['level'], self::LEVELS, true);
                if ($rank === false || $rank > $maxLevel || ($since !== null && $entry['at'] < $since)) {
                    continue;
                }
                if ($search !== null && ! str_contains(mb_strtolower($entry['message'].' '.($entry['exception'] ?? '')), $search)) {
                    continue;
                }
                $key = $entry['level'].'|'.($entry['exception'] ?? '').'|'.($entry['at_file'] ?? $entry['message']);
                $group = $groups[$key] ?? ['count' => 0, 'first_seen' => $entry['at'], 'last_seen' => ''];
                $first = is_string($group['first_seen']) ? $group['first_seen'] : $entry['at'];
                $last = is_string($group['last_seen']) ? $group['last_seen'] : '';
                $count = is_int($group['count']) ? $group['count'] : 0;
                // The newest occurrence's details.
                $groups[$key] = [...($entry['at'] >= $last ? $entry : $group), 'count' => $count + 1, 'first_seen' => min($first, $entry['at']), 'last_seen' => max($last, $entry['at'])];
            }
        }
        usort($groups, fn (array $a, array $b): int => strcmp(is_string($b['last_seen']) ? $b['last_seen'] : '', is_string($a['last_seen']) ? $a['last_seen'] : ''));

        return $this->json([
            'files' => array_map(self::relative(...), $files),
            'errors' => array_map(fn (array $group): array => array_filter([
                'level' => $group['level'] ?? null,
                'count' => $group['count'],
                'first_seen' => $group['first_seen'],
                'last_seen' => $group['last_seen'],
                'environment' => $group['environment'] ?? null,
                'message' => $group['message'] ?? null,
                'exception' => $group['exception'] ?? null,
                'at' => $group['at_file'] ?? null,
                'trace' => $group['trace'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== []), array_slice($groups, 0, $args['limit'] ?? 10)),
        ]);
    }

    /**
     * The newest log files.
     *
     * @return list<string>
     */
    private static function files(): array
    {
        $directory = config('sunrice-admin.ai.log_path');
        $directory = is_string($directory) && $directory !== '' ? $directory : storage_path('logs');
        $files = glob(rtrim($directory, '/').'/*.log') ?: [];
        usort($files, fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return array_slice($files, 0, self::MAX_FILES);
    }

    /**
     * One file's entries, from its last TAIL_BYTES.
     *
     * @return list<LogEntry>
     */
    private static function entries(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }
        fseek($handle, max(0, (filesize($file) ?: 0) - self::TAIL_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        $parts = preg_split('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] (\w+)\.(\w+): /m', $content, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $entries = [];
        // [0] is what comes before the first entry (cut by the tail), then four parts per entry.
        for ($i = 1; isset($parts[$i + 3]); $i += 4) {
            $entries[] = self::entry(str_replace('T', ' ', $parts[$i]), $parts[$i + 1], strtolower($parts[$i + 2]), $parts[$i + 3]);
        }

        return $entries;
    }

    /** @return LogEntry */
    private static function entry(string $at, string $environment, string $level, string $body): array
    {
        // The context is JSON: class names come as App\\Models\\Entry.
        $body = str_replace('\\\\', '\\', $body);
        $message = trim((string) strtok($body, "\n"));
        // Monolog appends the context and extra: '… {"exception":"[object] …' or '… [] []'.
        $message = trim((string) preg_replace('/\s+(\{"|\[\]).*$/s', '', $message)) ?: $message;
        $entry = ['at' => $at, 'environment' => $environment, 'level' => $level, 'message' => self::clean($message, 1000)];

        if (preg_match('/\[object\] \(([\w\\\\]+)\(code: [^)]*\): (.*?) at (\S+?):(\d+)\)/s', $body, $m) === 1) {
            $entry['exception'] = $m[1];
            $entry['at_file'] = self::relative($m[3]).':'.$m[4];
        }
        if (preg_match_all('/^#\d+ (.+)$/m', $body, $frames) > 0) {
            $all = array_map(fn (string $frame): string => self::clean(self::relative(trim($frame, '" ')), 300), $frames[1]);
            // The site's own files say most about a fix; the vendor's follow.
            $own = array_filter($all, fn (string $frame): bool => ! str_starts_with($frame, 'vendor/') && ! str_starts_with($frame, '[internal') && $frame !== '{main}');
            $entry['trace'] = array_slice(array_values(array_unique([...$own, ...$all])), 0, self::FRAMES);
        }

        return $entry;
    }

    /** Paths relative to the project: short, and pointing at files. */
    private static function relative(string $path): string
    {
        return str_replace(rtrim(base_path(), '/').'/', '', $path);
    }

    /** Masks secrets that end up in log messages, and caps the length. */
    public static function clean(string $text, int $max = 1000): string
    {
        $text = (string) preg_replace([
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
            '/\bsra_[A-Za-z0-9]{8,}/',
            '/\bbase64:[A-Za-z0-9+\/=]{20,}/',
            '#(://[^:/\s@]+):[^@\s]+@#',
            '/(["\']?(?:password|passwd|pwd|secret|token|api[_-]?key|authorization)["\']?\s*[:=]>?\s*)(["\']?)[^"\'\s,}&]+/i',
        ], [
            '$1 [masked]',
            'sra_[masked]',
            'base64:[masked]',
            '$1:[masked]@',
            '$1$2[masked]',
        ], $text);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).'…' : $text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'level' => $schema->string()->enum(self::LEVELS)->description('The lowest level to include (default error: error, critical, alert, emergency).'),
            'since_hours' => $schema->integer()->description('Only the last N hours.'),
            'search' => $schema->string()->description('Text in the message or exception class.'),
            'limit' => $schema->integer()->description('How many distinct errors (default 10, at most 50).'),
        ];
    }
}
