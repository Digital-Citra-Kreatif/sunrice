import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AppSidebar } from '@/components/app/app-sidebar';
import { AppHeader } from '@/components/app/app-header';
import { BreadcrumbProvider } from '@/components/app/breadcrumbs';
import { useBrandColor } from '@/components/app/brand';
import type { SharedProps } from '@/types';

/** The sidebar's open/collapsed state, kept in a cookie by the sidebar. */
function sidebarDefaultOpen(): boolean {
    return typeof document === 'undefined' || !document.cookie.split('; ').includes('sidebar_state=false');
}

/** The admin shell around every page: sidebar, header and toasts. */
export default function AppLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { flash } = page.props;
    useBrandColor();

    React.useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    // A rejected form is never silent: pages show field errors inline, this covers the rest.
    React.useEffect(
        () =>
            router.on('error', (event) => {
                const messages = Object.values(event.detail.errors ?? {}).filter(Boolean) as string[];
                if (messages.length > 0) {
                    toast.error(messages.length === 1 ? messages[0] : `${messages[0]} (+${messages.length - 1} more)`);
                }
            }),
        [],
    );

    return (
        <TooltipProvider delayDuration={0}>
            <BreadcrumbProvider>
                <SidebarProvider defaultOpen={sidebarDefaultOpen()}>
                    <AppSidebar />
                    <SidebarInset className="min-w-0 overflow-x-clip border border-border/60">
                        <AppHeader />
                        <main key={page.component} className="mx-auto flex w-full max-w-7xl flex-1 flex-col p-5 md:p-8 lg:p-10">{children}</main>
                    </SidebarInset>
                    <Toaster position="top-right" />
                </SidebarProvider>
            </BreadcrumbProvider>
        </TooltipProvider>
    );
}
