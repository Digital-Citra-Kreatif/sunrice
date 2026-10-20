import { formatDistance } from 'date-fns';

/** A dashboard widget as the page receives it (Sunrice\Admin\Dashboard\Widget::definition()). */
export interface WidgetDefinition {
    key: string;
    type: 'stat' | 'list' | 'cards';
    title: string;
    description: string | null;
    icon: string | null;
    link: { url: string; label: string | null } | null;
    empty: string | null;
    span: number;
    prop: string;
}

export interface StatData {
    value: number | string;
    delta: string | null;
    trend: 'up' | 'down' | null;
    description: string | null;
}

export type Tone = 'neutral' | 'success' | 'warning' | 'danger';

export interface ListRow {
    title: string;
    meta: string | null;
    url: string | null;
    badge: string | null;
    tone: Tone;
    at: string | null;
}

export interface ListData {
    rows: ListRow[];
    count: number | null;
}

export interface CardsData {
    cards: { title: string; text: string | null; url: string | null; icon: string | null }[];
}

/** What a deferred widget prop holds: its data, or `ok: false` when it failed on the server. */
export type WidgetResult<T = StatData | ListData | CardsData> = { ok: true; data: T } | { ok: false };

/**
 * Where a widget link goes: "/…" and http(s) addresses as they are, a
 * path relative to the panel under it ("shop/orders" → "/admin/shop/orders").
 */
export function widgetHref(url: string, adminUrl: (path: string) => string): string {
    return url.startsWith('/') || /^https?:\/\//i.test(url) ? url : adminUrl(url);
}

/** Whether a link leaves the panel (opened as a plain link, not an Inertia visit). */
export function isExternal(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

/** Grid classes for a widget's span on the three-column grid (one column on phones, two on tablets). */
export function spanClass(span: number): string {
    if (span >= 3) return 'md:col-span-2 xl:col-span-3';
    if (span === 2) return 'md:col-span-2';

    return '';
}

/** "Good morning" … by the hour. */
export function greeting(date: Date): string {
    const hour = date.getHours();
    if (hour < 5) return 'Good evening';
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';

    return 'Good evening';
}

/** A number with thousands separators; text as it is. */
export function formatStat(value: number | string, locale?: string): string {
    return typeof value === 'number' ? new Intl.NumberFormat(locale).format(value) : value;
}

/** "5 minutes ago", "in 3 days"; empty for a missing or broken date. */
export function relativeTime(iso: string | null, now: Date): string {
    if (!iso) return '';
    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? '' : formatDistance(date, now, { addSuffix: true });
}

interface NavLinkLike {
    key: string;
    label: string;
    url: string;
    icon: string;
}

/** The hero's quick links: the first top-level items of the sidebar, the administration ones last. */
export function quickLinks(navigation: readonly { group: string | null; items: readonly NavLinkLike[] }[], max = 4): NavLinkLike[] {
    const groups = [...navigation].sort((a, b) => Number(a.group === 'Administration') - Number(b.group === 'Administration'));

    return groups.flatMap((group) => group.items.map(({ key, label, url, icon }) => ({ key, label, url, icon }))).slice(0, max);
}
