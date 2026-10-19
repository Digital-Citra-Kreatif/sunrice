import * as React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import type { SharedProps } from '@/types';

/** The sign-in pages: the brand, a title, and the form. */
export default function AuthLayout({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    const { sunrice, flash } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background px-4 py-12">
            <Head title={title} />
            <div className="w-full max-w-sm">
                <div className="mb-10 flex items-center gap-2 text-lg font-semibold tracking-tight">
                    <span className="flex size-8 items-center justify-center rounded-md bg-primary text-sm text-primary-foreground">
                        {sunrice.brand.slice(0, 1).toUpperCase()}
                    </span>
                    {sunrice.brand}
                </div>
                <div className="flex flex-col gap-8">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-medium tracking-[-0.04em]">{title}</h1>
                        {description && <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>}
                    </div>
                    {flash.success && <p role="status" className="rounded-md bg-muted px-3 py-2 text-sm">{flash.success}</p>}
                    {flash.error && <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{flash.error}</p>}
                    {children}
                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}
