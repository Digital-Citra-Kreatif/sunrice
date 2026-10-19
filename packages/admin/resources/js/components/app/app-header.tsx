import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useBreadcrumbOverride } from '@/components/app/breadcrumbs';

/** The bar above each page: the sidebar toggle and the page's breadcrumbs (useBreadcrumbs). */
export function AppHeader() {
    const crumbs = useBreadcrumbOverride() ?? [];

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/60 px-5 md:px-8 lg:px-10">
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
        </header>
    );
}
