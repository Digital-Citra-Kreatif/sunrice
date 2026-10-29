import * as React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AuthBrand, useBranding } from '@/components/app/app-logo';
import { AtmosphereArt } from '@/components/app/atmosphere-art';
import { Toaster } from '@/components/ui/sonner';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

/**
 * The sign-in pages (log in, two-factor code, forgot and reset password), branded in Settings → Branding:
 * a side panel with the sign-in copy and an image or the atmosphere art, or the form alone, centered.
 */
export default function AuthLayout({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    const { flash } = usePage<SharedProps>().props;
    const brand = useBranding();
    const { login } = brand;
    const split = login.layout !== 'centered';
    const [firstLine, ...moreLines] = login.headline.split('\n');
    // The logo and name as Settings → Branding → Sidebar shows says.
    const mark = <AuthBrand />;

    return (
        <div className={cn('sunrice-shell grid min-h-svh bg-background p-4', split && 'lg:grid-cols-2 lg:gap-4')}>
            <Head title={title} />
            {split && (
                <aside className="sunrice-atmosphere sunrice-page-enter relative hidden flex-col justify-between gap-8 overflow-hidden rounded-xl p-10 lg:flex lg:min-h-[calc(100svh-2rem)] xl:p-14">
                    {mark}
                    <div className="relative z-10">
                        <p className="mb-5 text-xs font-medium tracking-[0.18em] uppercase opacity-80">{login.eyebrow}</p>
                        <h2 className="max-w-md text-4xl leading-[1.08] font-medium tracking-[-0.045em] xl:text-5xl">
                            {firstLine}
                            {moreLines.map((line, i) => (
                                <React.Fragment key={i}><br /><span className="opacity-60">{line}</span></React.Fragment>
                            ))}
                        </h2>
                        <p className="mt-6 max-w-xs text-sm leading-relaxed opacity-70">{login.text}</p>
                    </div>
                    {login.image
                        ? <img src={login.image} alt="" className="relative z-10 max-h-80 w-full rounded-xl object-cover" />
                        : <AtmosphereArt className="h-64 w-full self-center xl:h-72" />}
                    <p className="relative z-10 text-xs opacity-60">{login.footer}</p>
                </aside>
            )}
            <div className="flex min-h-[calc(100svh-2rem)] flex-col items-center justify-center px-4 py-12 sm:px-8">
                {/* Centered: the form floats on a card; beside the side panel it stands on the page. */}
                <div className={cn('sunrice-page-enter w-full max-w-sm', !split && 'sm:max-w-[26rem] sm:rounded-2xl sm:bg-card sm:p-10 sm:shadow-pop')}>
                    <div className={cn('mb-10', split && 'lg:hidden')}>{mark}</div>
                    <div className="flex flex-col gap-8">
                        <div className="space-y-2">
                            {brand.tagline && <p className="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">{brand.tagline}</p>}
                            <h1 className="sunrice-page-title">{title}</h1>
                            {description && <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>}
                        </div>
                        {flash.success && <p role="status" className="rounded-md bg-muted px-3 py-2 text-sm">{flash.success}</p>}
                        {flash.error && <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{flash.error}</p>}
                        {children}
                    </div>
                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}
