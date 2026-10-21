import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AppSidebar } from '@/components/app/app-sidebar';
import { AppHeader } from '@/components/app/app-header';
import { BreadcrumbProvider } from '@/components/app/breadcrumbs';
import { CommandPalette, isCommandPaletteShortcut, setCommandPaletteOpen, useCommandPaletteOpen } from '@/components/app/command-palette';
import type { SharedProps } from '@/types';
import { t } from '@/lib/i18n';

/** The sidebar's open/collapsed state, kept in a cookie by the sidebar. */
function sidebarDefaultOpen(): boolean {
    return typeof document === 'undefined' || !document.cookie.split('; ').includes('sidebar_state=false');
}

/** The admin shell around every page: sidebar, header and toasts. */
export default function AppLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
    const paletteOpen = useCommandPaletteOpen();

    // Cmd/Ctrl+K opens the command palette from anywhere in the admin.
    React.useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (!isCommandPaletteShortcut(event)) return;
            event.preventDefault();
            setCommandPaletteOpen(!paletteOpen);
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [paletteOpen]);

    // Each message once: keyed by its id, so the same text twice toasts twice.
    React.useEffect(() => {
        if (flash.success) toast.success(flash.success, flash.id ? { id: `${flash.id}:success` } : undefined);
        if (flash.error) toast.error(flash.error, flash.id ? { id: `${flash.id}:error` } : undefined);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash.id, flash.success, flash.error]);

    // A rejected form is never silent: pages show field errors inline, this covers the rest.
    React.useEffect(
        () =>
            router.on('error', (event) => {
                const messages = Object.values(event.detail.errors ?? {}).filter(Boolean) as string[];
                if (messages.length > 0) {
                    toast.error(messages.length === 1 ? messages[0] : t(':message (+:count more)', { message: messages[0], count: messages.length - 1 }));
                }
            }),
        [],
    );

    return (
        <TooltipProvider delayDuration={0}>
            <BreadcrumbProvider>
                <SidebarProvider defaultOpen={sidebarDefaultOpen()}>
                    <AppSidebar />
                    <SidebarInset className="min-w-0 overflow-x-clip border border-border/60 shadow-[0_4px_30px_-12px_rgb(0_0_0/0.08)]">
                        <AppHeader />
                        <main key={page.component} className="sunrice-page-enter mx-auto flex w-full max-w-screen-2xl flex-1 flex-col p-5 md:p-8 lg:p-10">{children}</main>
                    </SidebarInset>
                    <Toaster position="top-right" />
                    <CommandPalette />
                </SidebarProvider>
            </BreadcrumbProvider>
        </TooltipProvider>
    );
}
