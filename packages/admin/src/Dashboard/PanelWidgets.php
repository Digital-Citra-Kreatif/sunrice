<?php

declare(strict_types=1);

namespace Sunrice\Admin\Dashboard;

use Illuminate\Support\Str;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;

/**
 * The panel's own dashboard widgets: recent activity and the media
 * library's size. One query each.
 *
 * @internal
 */
final class PanelWidgets
{
    public const RECENT = 8;

    public static function activity(): Widget
    {
        return Widget::list('sunrice.activity')->title('Recent activity')->icon('history')
            ->sort(900)->span(2)->permission('sunrice.activity.view')
            ->link('activity', 'Open the log')->empty('Nothing has happened yet.')
            ->data(fn (): array => ['rows' => Activity::query()->orderByDesc('id')->limit(self::RECENT)->get()
                ->map(fn (Activity $line): array => [
                    'title' => trim(($line->user_name ?? ($line->source === 'system' ? 'System' : 'Someone')).' '.$line->action.' '.($line->subject_label ?? '')),
                    'meta' => self::typeLabel($line->subject_type).($line->source === 'ai' && $line->via !== null ? " · via {$line->via}" : ''),
                    'url' => $line->subject_id === null ? 'activity' : 'activity?'.http_build_query(['type' => $line->subject_type, 'subject' => $line->subject_id]),
                    'badge' => $line->source === 'admin' ? null : Str::ucfirst($line->source === 'ai' ? 'AI' : $line->source),
                    'tone' => $line->source === 'security' ? 'warning' : 'neutral',
                    'at' => $line->created_at?->toIso8601String(),
                ])->all()]);
    }

    public static function media(): Widget
    {
        return Widget::stat('sunrice.media')->title('Media')->icon('image')
            ->sort(60)->permission('sunrice.media.view')->link('media')
            ->data(function (): array {
                $row = Media::query()->toBase()
                    ->selectRaw('count(*) as total, sum(case when created_at >= ? then 1 else 0 end) as recent', [now()->subWeek()])
                    ->first();
                $total = is_object($row) && is_numeric($row->total ?? null) ? (int) $row->total : 0;
                $recent = is_object($row) && is_numeric($row->recent ?? null) ? (int) $row->recent : 0;

                return [
                    'value' => $total,
                    'delta' => $recent > 0 ? "+{$recent} this week" : null,
                    'trend' => $recent > 0 ? 'up' : null,
                    'description' => $total === 1 ? 'file in the library' : 'files in the library',
                ];
            });
    }

    /** "Product" for the products resource, "Settings", "Media" … as the activity log names them. */
    private static function typeLabel(string $type): string
    {
        $resource = app(Panel::class)->getResources()[$type] ?? null;

        return $resource !== null ? $resource->class::singularLabel() : Str::ucfirst($type);
    }
}
