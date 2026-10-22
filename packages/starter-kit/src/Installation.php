<?php

declare(strict_types=1);

namespace Sunrice\StarterKit;

use Sunrice\Cms\Structure\ImportReport;

/**
 * What installing a starter did, or would do on a dry run: the structure
 * import's report, the files copied into the app (and those kept as they
 * were) and the media library's files uploaded (and those it had).
 *
 * @api
 */
final class Installation
{
    /** Copied, or a dry run's "would be copied". */
    public const COPIED = 'copied';

    /** Already there, the same. */
    public const UNCHANGED = 'unchanged';

    /** There, different, and kept (without --force). */
    public const KEPT = 'kept';

    /**
     * @param  array<string, string>  $files  app-relative path => COPIED, UNCHANGED or KEPT
     * @param  list<string>  $uploaded  media files uploaded (a dry run: to upload)
     * @param  list<string>  $reused  media files the library had
     */
    public function __construct(
        public readonly Starter $starter,
        public readonly ImportReport $report,
        public readonly array $files,
        public readonly array $uploaded,
        public readonly array $reused,
        public readonly bool $dryRun,
    ) {}

    /** @return list<string> the files with this result */
    public function files(string $result): array
    {
        return array_keys(array_filter($this->files, fn (string $status): bool => $status === $result));
    }
}
