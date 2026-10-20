import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useBreadcrumbOverride } from '@/components/app/breadcrumbs';
import { setCommandPaletteOpen } from '@/components/app/command-palette';

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/** The bar above each page: the sidebar toggle and the page's breadcrumbs (useBreadcrumbs). */
export function AppHeader() {
    const crumbs = useBreadcrumbOverride() ?? [];

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-border/60 bg-background/70 px-5 backdrop-blur-md md:px-8 lg:px-10">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
                <BreadcrumbList>
                    {crumbs.map((crumb, i) => (
                        <React.Fragment key={i}>
                            {i > 0 && <BreadcrumbSeparator />}
                            <BreadcrumbItem>
                                {crumb.href && i < crumbs.length - 1 ? (
                                    <BreadcrumbLink asChild><Link href={crumb.href}>{crumb.label}</Link></BreadcrumbLink>
                                ) : (
                                    <BreadcrumbPage className="max-w-48 truncate">{crumb.label}</BreadcrumbPage>
                                )}
                            </BreadcrumbItem>
                        </React.Fragment>
                    ))}
                </BreadcrumbList>
            </Breadcrumb>
            <Button variant="outline" size="sm" className="ml-auto gap-2 text-muted-foreground" onClick={() => setCommandPaletteOpen(true)} aria-label="Go to a page or search" aria-keyshortcuts="Meta+K Control+K">
                <Search />
                <span className="hidden sm:inline">Go to…</span>
                <kbd className="hidden rounded border border-border/70 bg-muted px-1.5 py-0.5 font-sans text-[10px] font-medium text-muted-foreground sm:inline">{isMac ? '⌘' : 'Ctrl'} K</kbd>
            </Button>
        </header>
    );
}
