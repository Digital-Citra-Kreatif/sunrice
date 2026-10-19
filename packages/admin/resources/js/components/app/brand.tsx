import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

/** The brand's logo, or its initial on the brand colour. */
export function BrandMark({ className }: { className?: string }) {
    const { sunrice } = usePage<SharedProps>().props;

    return sunrice.logo ? (
        <img src={sunrice.logo} alt="" className={cn('size-8 shrink-0 rounded-md object-contain', className)} />
    ) : (
        <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground', className)}>
            {sunrice.brand.slice(0, 1).toUpperCase()}
        </span>
    );
}

/** Settings → Branding's colour on buttons, highlights and focus rings, in light and dark. */
export function useBrandColor() {
    const { sunrice } = usePage<SharedProps>().props;

    React.useEffect(() => {
        const root = document.documentElement.style;
        const names = ['--primary', '--primary-foreground', '--ring', '--sidebar-primary', '--sidebar-primary-foreground'];
        if (!sunrice.color) {
            names.forEach((name) => root.removeProperty(name));
            return;
        }
        root.setProperty('--primary', sunrice.color);
        root.setProperty('--sidebar-primary', sunrice.color);
        root.setProperty('--ring', sunrice.color);
        root.setProperty('--primary-foreground', sunrice.colorText ?? '#ffffff');
        root.setProperty('--sidebar-primary-foreground', sunrice.colorText ?? '#ffffff');
    }, [sunrice.color, sunrice.colorText]);
}
