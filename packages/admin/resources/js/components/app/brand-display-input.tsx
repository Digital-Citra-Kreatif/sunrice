import * as React from 'react';
import { AppLogo, useBranding } from '@/components/app/app-logo';
import type { FieldValue } from '@/components/resources/fields';
import { type BrandDisplay, brandPreviewQuery, readableOn } from '@/lib/branding';
import { fetchJson, withQuery } from '@/lib/fetch-json';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface BrandDisplayOption {
    value: string;
    label: string;
    description: string;
}

/** BrandingPreviewController: the branding with the form's unsaved values, and what each choice shows. */
interface Preview {
    name: string;
    tagline: string;
    logo: string | null;
    logoWide: boolean;
    mark: string | null;
    lettermark: string;
    color: string | null;
    displays: Record<string, BrandDisplay>;
}

interface Props {
    id: string;
    options: BrandDisplayOption[];
    previewUrl: string;
    value: string | null;
    /** The whole form: the name, tagline, logo, icon and colour the previews show. */
    data: Record<string, FieldValue>;
    disabled: boolean;
    invalid: boolean;
    onChange: (value: string) => void;
}

/**
 * Settings → Branding → Sidebar shows: each choice as a card with the sidebar's header drawn with the form's
 * values, and the collapsed sidebar's square beside them. The server resolves the images (the same code as
 * the sidebar and the favicon), so the preview can't disagree with what saving shows.
 */
export function BrandDisplayInput({ id, options, previewUrl, value, data, disabled, invalid, onChange }: Props) {
    const saved = useBranding();
    const [preview, setPreview] = React.useState<Preview | null>(null);
    const query = brandPreviewQuery(data);
    const key = JSON.stringify(query);

    React.useEffect(() => {
        let current = true;
        // Typing in the name: ask once it pauses.
        const timer = window.setTimeout(() => {
            fetchJson<Preview>(withQuery(previewUrl, query))
                .then((next) => { if (current) setPreview(next); })
                .catch(() => { /* The preview is a nicety: keep the last one. */ });
        }, 250);
        return () => { current = false; window.clearTimeout(timer); };
    }, [previewUrl, key]); // eslint-disable-line react-hooks/exhaustive-deps

    const brand = preview ?? { ...saved, logoWide: saved.logoWide === true, displays: null };
    const chosen = value ?? 'auto';
    // An unsaved colour: the lettermark's tile in the preview follows it.
    const style = brand.color
        ? ({ '--sidebar-primary': brand.color, '--sidebar-primary-foreground': readableOn(brand.color) } as React.CSSProperties)
        : undefined;
    const header = (option: string, collapsed: boolean) => (
        <div className="flex h-12 min-w-0 items-center gap-2 overflow-hidden rounded-md bg-sidebar p-2 text-sm text-sidebar-foreground ring-1 ring-sidebar-border" style={style}>
            <AppLogo collapsed={collapsed} branding={{ ...brand, display: brand.displays?.[option] ?? saved.display }} />
        </div>
    );

    return (
        <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
            <div id={id} role="radiogroup" aria-labelledby={`${id}-label`} aria-invalid={invalid} className={cn('grid gap-2 sm:grid-cols-2', invalid && 'rounded-md ring-1 ring-destructive')}>
                {options.map((option) => {
                    const checked = chosen === option.value;
                    return (
                        <label key={option.value}
                            className={cn('grid cursor-pointer content-start gap-2 rounded-lg border p-3 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                checked ? 'border-primary bg-accent/40' : 'hover:bg-muted/50', disabled && 'cursor-not-allowed opacity-60')}>
                            <span className="flex items-start gap-2">
                                <input type="radio" name={id} value={option.value} checked={checked} disabled={disabled}
                                    onChange={() => onChange(option.value)} className="mt-0.5 accent-primary" />
                                <span className="grid gap-0.5">
                                    <span className="text-sm font-medium">{option.label}</span>
                                    <span className="text-xs text-muted-foreground">{option.description}</span>
                                </span>
                            </span>
                            {header(option.value, false)}
                        </label>
                    );
                })}
            </div>
            <div className="grid content-start justify-items-start gap-2 self-start rounded-lg border border-dashed p-3">
                <span className="text-sm font-medium">{t('Collapsed sidebar')}</span>
                <div className="w-12">{header(chosen, true)}</div>
                <span className="max-w-40 text-xs text-muted-foreground">{t('The same square as the browser tab.')}</span>
            </div>
        </div>
    );
}
