import { Link } from '@inertiajs/react';
import { useScrollFade } from '@/hooks/use-scroll-fade';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface SettingsNavEntry {
    key: string;
    label: string;
    /** Its address under the panel: "settings/site", "cms/seo". */
    url: string;
}

/**
 * The Settings menu beside a settings page: the sections and the pages put
 * among them (`Panel::settingsNavigation()` gives `items`). A column on wide
 * screens, a scrolling row on narrow ones. `current` is the key shown.
 */
export function SettingsNav({ items, current }: { items: SettingsNavEntry[]; current: string }) {
    const tabs = useScrollFade<HTMLElement>('x', '[aria-current="page"]', current);
    if (items.length <= 1) return null;

    return (
        <nav ref={tabs} aria-label={t('Settings sections')} className="sunrice-scroll-fade-x -mx-1 flex shrink-0 snap-x snap-proximity scroll-px-1 gap-1 overflow-x-auto px-1 [scrollbar-width:none] lg:mx-0 lg:w-48 lg:flex-col lg:px-0">
            {items.map((item) => (
                <Link key={item.key} href={adminUrl(item.url)} aria-current={item.key === current ? 'page' : undefined}
                    className={cn('shrink-0 snap-start rounded-md px-3 py-2 text-sm whitespace-nowrap hover:bg-muted', item.key === current && 'bg-muted font-medium')}>
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}

/** A settings page's layout: the Settings menu, then the page. */
export function SettingsLayout({ items, current, children }: { items: SettingsNavEntry[]; current: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-6 lg:flex-row">
            <SettingsNav items={items} current={current} />
            <div className="flex max-w-5xl min-w-0 flex-1 flex-col gap-6">{children}</div>
        </div>
    );
}
