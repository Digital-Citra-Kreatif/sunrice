import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Branding, SharedProps } from '@/types';

/** The panel's branding (Settings → Branding), shared with every page. */
export function useBranding(): Branding {
    return usePage<SharedProps>().props.sunrice.branding;
}

/** The logo when one is set, else the name's first letter on the brand colour. Decorative: the name is shown beside it. */
export function AppLogoIcon({ className }: { className?: string }) {
    const { logo, lettermark } = useBranding();

    return (
        <span
            className={cn(
                'flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg text-sm font-semibold',
                logo ? 'bg-card' : 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm',
                className,
            )}
        >
            {logo ? <img src={logo} alt="" className="size-full object-contain" /> : <span aria-hidden="true">{lettermark}</span>}
        </span>
    );
}

/** The logo (or lettermark), the name and the tagline, as at the top of the sidebar. */
export function AppLogo() {
    const { name, tagline } = useBranding();

    return (
        <>
            <AppLogoIcon />
            <span className="grid min-w-0 flex-1 text-left leading-tight">
                <span className="truncate font-semibold tracking-tight">{name}</span>
                {tagline && <span className="truncate text-[11px] text-muted-foreground">{tagline}</span>}
            </span>
        </>
    );
}
