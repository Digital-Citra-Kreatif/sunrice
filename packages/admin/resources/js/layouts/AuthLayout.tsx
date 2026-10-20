import * as React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AtmosphereArt } from '@/components/app/atmosphere-art';
import { Toaster } from '@/components/ui/sonner';
import type { SharedProps } from '@/types';

/** The sign-in pages: a quiet side panel on large screens, and the brand, a title and the form. */
export default function AuthLayout({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    const { sunrice, flash } = usePage<SharedProps>().props;
    const mark = (
        <>
            <span className="flex size-8 items-center justify-center rounded-md bg-primary text-sm text-primary-foreground">
                {sunrice.brand.slice(0, 1).toUpperCase()}
            </span>
            {sunrice.brand}
        </>
    );

    return (
        <div className="sunrice-shell grid min-h-svh bg-background p-4 lg:grid-cols-2 lg:gap-4">
            <Head title={title} />
            <aside className="sunrice-atmosphere sunrice-page-enter relative hidden flex-col justify-between overflow-hidden rounded-xl p-10 lg:flex lg:min-h-[calc(100svh-2rem)] xl:p-14">
                <div className="flex items-center gap-2 text-lg font-semibold tracking-tight">{mark}</div>
                <AtmosphereArt className="my-4 h-72 w-full self-center xl:h-80" />
                <p className="relative z-10 max-w-xs text-sm leading-relaxed opacity-70">Everything the site needs, in one calm place.</p>
            </aside>
            <div className="flex flex-col items-center justify-center px-4 py-12 sm:px-8">
                <div className="sunrice-page-enter w-full max-w-sm">
                    <div className="mb-10 flex items-center gap-2 text-lg font-semibold tracking-tight lg:hidden">{mark}</div>
                    <div className="flex flex-col gap-8">
                        <div className="space-y-2">
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
