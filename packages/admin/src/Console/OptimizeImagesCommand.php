<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Sunrice\Admin\Actions\Media\OptimizeImage;
use Sunrice\Admin\Actions\Media\RestoreOriginalImage;
use Sunrice\Admin\Media\Media;

/**
 * sunrice:optimize-images — shrink and compress images in place, keeping
 * the originals (or put them back with --restore).
 *
 * @internal
 */
final class OptimizeImagesCommand extends Command
{
    protected $signature = 'sunrice:optimize-images
        {--id=* : Only these media ids (repeat for several)}
        {--max-width= : Scale down images wider than this}
        {--max-height= : Scale down images taller than this}
        {--quality= : JPEG, WebP and AVIF quality, 1 to 100}
        {--no-backup : Don\'t keep the originals}
        {--dry-run : Show what would change, write nothing}
        {--restore : Put the kept originals back instead}';

    protected $description = 'Shrink and compress the media library\'s images in place, keeping the originals';

    public function handle(OptimizeImage $optimizer, RestoreOriginalImage $restorer): int
    {
        $maxWidth = self::number($this->option('max-width'), 'max_width', 2560);
        $maxHeight = self::number($this->option('max-height'), 'max_height', 2560);
        $quality = self::number($this->option('quality'), 'quality', 82);
        $backup = $this->option('no-backup') !== true && config('sunrice-admin.media.optimize.backup', true) !== false;
        $dryRun = $this->option('dry-run') === true;
        if ($maxWidth < 1 || $maxHeight < 1 || $quality < 1 || $quality > 100) {
            $this->components->error('--max-width and --max-height must be positive, and --quality between 1 and 100.');

            return self::FAILURE;
        }

        $query = Media::query()->where('mime_type', 'like', 'image/%');
        $ids = array_values(array_filter(array_map(intval(...), (array) $this->option('id'))));
        if ($ids !== []) {
            $query->whereKey($ids);
        }

        return $this->option('restore') === true
            ? $this->restore($query, $restorer)
            : $this->optimize($query, $optimizer, $maxWidth, $maxHeight, $quality, $backup, $dryRun);
    }

    /** @param Builder<Media> $query */
    private function optimize(Builder $query, OptimizeImage $optimizer, int $maxWidth, int $maxHeight, int $quality, bool $backup, bool $dryRun): int
    {
        $this->line(sprintf('%s images to fit %dx%d at quality %d%s.', $dryRun ? 'Checking' : 'Optimizing', $maxWidth, $maxHeight, $quality,
            $dryRun ? ' (a dry run: nothing is written)' : ($backup ? ', keeping the originals' : ', without keeping the originals')));
        $optimized = $skipped = $saved = 0;
        $query->chunkById(50, function ($files) use ($optimizer, $maxWidth, $maxHeight, $quality, $backup, $dryRun, &$optimized, &$skipped, &$saved): void {
            foreach ($files as $media) {
                try {
                    $result = $optimizer->handle($media, $maxWidth, $maxHeight, $quality, $backup, $dryRun);
                } catch (\Throwable $e) {
                    $skipped++;
                    $this->components->warn("#{$media->id} {$media->filename}: failed ({$e->getMessage()})");

                    continue;
                }
                if ($result['status'] !== 'optimized') {
                    $skipped++;
                    $this->line("  #{$media->id} {$media->filename}: skipped ({$result['reason']})", verbosity: 'v');

                    continue;
                }
                $optimized++;
                $saved += $result['before'] - $result['after'];
                $this->line(sprintf('  #%d %s: %s → %s, %dx%d', $media->id, $media->filename, self::bytes($result['before']), self::bytes($result['after']), $result['width'], $result['height']));
            }
        });
        $this->components->info(sprintf('%s %d %s, skipped %d, %s %s.', $dryRun ? 'Would optimize' : 'Optimized', $optimized, $optimized === 1 ? 'image' : 'images',
            $skipped, $dryRun ? 'would save' : 'saved', self::bytes(max(0, $saved))));

        return self::SUCCESS;
    }

    /** @param Builder<Media> $query */
    private function restore(Builder $query, RestoreOriginalImage $restorer): int
    {
        $restored = 0;
        $query->chunkById(50, function ($files) use ($restorer, &$restored): void {
            foreach ($files as $media) {
                if ($restorer->handle($media)) {
                    $restored++;
                    $this->line("  #{$media->id} {$media->filename}: restored");
                }
            }
        });
        $this->components->info("Put back {$restored} ".($restored === 1 ? 'original.' : 'originals.'));

        return self::SUCCESS;
    }

    private static function number(mixed $option, string $key, int $default): int
    {
        $value = $option ?? config("sunrice-admin.media.optimize.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }
}
