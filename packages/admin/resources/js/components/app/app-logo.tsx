import { usePage } from '@inertiajs/react';
import { brandHeader } from '@/lib/branding';
import { cn } from '@/lib/utils';
import type { Branding, SharedProps } from '@/types';

/** The panel's branding (Settings → Branding), shared with every page. */
export function useBranding(): Branding {
    return usePage<SharedProps>().props.sunrice.branding;
}

type BrandParts = Pick<Branding, 'name' | 'tagline' | 'logo' | 'logoWide' | 'display' | 'mark' | 'lettermark'>;

/** A square mark: the image, or the name's first letter on the brand colour. Decorative: the name is shown beside it or in a tooltip. */
export function BrandMark({ src, lettermark, className }: { src: string | null; lettermark: string; className?: string }) {
    return (
        <span
            className={cn(
                'flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg text-sm font-semibold',
                src ? 'bg-card' : 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm',
                className,
            )}
        >
            {src ? <img src={src} alt="" className="size-full object-contain" /> : <span aria-hidden="true">{lettermark}</span>}
        </span>
    );
}

/** The square mark beside the name, as Sidebar shows says (never a wide logo). */
export function AppLogoIcon({ className }: { className?: string }) {
    const branding = useBranding();

    return <BrandMark src={brandHeader(branding).mark} lettermark={branding.lettermark} className={className} />;
}

/** A wide logo (a wordmark) at its own proportions, in place of the name, which it holds. */
export function AppWordmark({ className, branding }: { className?: string; branding?: Pick<Branding, 'logo' | 'name'> }) {
    const current = useBranding();
    const { logo, name } = branding ?? current;

    return logo ? <img src={logo} alt={name} className={cn('h-8 w-auto max-w-full object-contain object-left', className)} /> : null;
}

/**
 * The top of the sidebar, as Settings → Branding → Sidebar shows says: the logo alone, or a square mark
 * (logo, browser icon or lettermark) and/or the name and tagline. The collapsed sidebar shows the favicon's
 * square mark. `collapsed` draws one state only (the settings preview); without it, the sidebar's state decides.
 */
export function AppLogo({ branding, collapsed }: { branding?: BrandParts; collapsed?: boolean }) {
    const current = useBranding();
    const brand = branding ?? current;
    const header = brandHeader(brand);

    const rail = <BrandMark src={header.collapsedMark} lettermark={brand.lettermark} />;
    const full = header.wordmark ? (
        <AppWordmark branding={{ logo: header.wordmark, name: brand.name }} />
    ) : (
        <>
            {header.showMark && <BrandMark src={header.mark} lettermark={brand.lettermark} />}
            <span className="grid min-w-0 flex-1 text-left leading-tight">
                <span className="truncate font-semibold tracking-tight">{brand.name}</span>
                {brand.tagline && <span className="truncate text-[11px] text-muted-foreground">{brand.tagline}</span>}
            </span>
        </>
    );

    if (collapsed === true) return rail;
    if (collapsed === false) return full;

    return (
        <>
            <span className="contents group-data-[collapsible=icon]:hidden">{full}</span>
            {/* The collapsed sidebar has room for a square only: the same mark as the browser tab. */}
            <span className="hidden group-data-[collapsible=icon]:contents">{rail}</span>
        </>
    );
}

/** The sign-in page's brand: the logo alone, or the mark and/or the name, as in the sidebar. */
export function AuthBrand() {
    const brand = useBranding();
    const header = brandHeader(brand);

    if (header.wordmark) {
        return <div className="flex items-center"><AppWordmark className="h-9" /></div>;
    }

    return (
        <div className="flex items-center gap-2.5 text-lg font-semibold tracking-tight">
            {header.showMark && <BrandMark src={header.mark} lettermark={brand.lettermark} />}
            <span className="truncate">{brand.name}</span>
        </div>
    );
}
