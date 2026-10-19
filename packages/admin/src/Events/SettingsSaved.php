<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A settings section was saved in the panel and these settings changed
 * (names only: values may be secrets).
 *
 * @api
 */
final class SettingsSaved
{
    use Dispatchable;

    /** @param list<string> $changed */
    public function __construct(
        public readonly string $section,
        public readonly array $changed,
    ) {}
}
