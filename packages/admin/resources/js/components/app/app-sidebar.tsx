import { BrandMark } from '@/components/app/brand';
import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, ChevronsUpDown, LayoutGrid, LogOut } from 'lucide-react';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, SidebarRail, useSidebar,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { navIcon } from '@/components/app/nav-icon';
import { adminUrl } from '@/lib/admin-url';
import type { NavItem, SharedProps } from '@/types';

/** Whether the current URL is this link's page or one below it. */
function isCurrent(currentPath: string, url: string): boolean {
    const href = adminUrl(url);
    return currentPath === href || (url !== '' && currentPath.startsWith(`${href}/`));
}

export function AppSidebar() {
    const { navigation, sunrice, auth } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0] ?? '';
    const { isMobile, setOpenMobile } = useSidebar();

    // On phones the sidebar is a flyout: close it when a visit starts.
    React.useEffect(() => {
        if (!isMobile) return;
        return router.on('start', () => setOpenMobile(false));
    }, [isMobile, setOpenMobile]);

    const item = (entry: NavItem) => {
        const Icon = navIcon(entry.icon);
        const open = entry.children.some((child) => isCurrent(path, child.url));
        if (entry.children.length === 0) {
            return (
                <SidebarMenuItem key={entry.key}>
                    <SidebarMenuButton asChild isActive={isCurrent(path, entry.url)} tooltip={entry.label}>
                        <Link href={adminUrl(entry.url)} prefetch><Icon /><span>{entry.label}</span></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            );
        }

        return (
            <Collapsible key={entry.key} asChild defaultOpen={open} className="group/submenu">
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton tooltip={entry.label} isActive={isCurrent(path, entry.url) && !open}>
                            <Icon />
                            <span>{entry.label}</span>
                            <ChevronRight className="ml-auto transition-transform group-data-[state=open]/submenu:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {[{ key: `${entry.key}:self`, label: entry.label, url: entry.url }, ...entry.children].map((child) => (
                                <SidebarMenuSubItem key={child.key}>
                                    <SidebarMenuSubButton asChild isActive={adminUrl(child.url) === path}>
                                        <Link href={adminUrl(child.url)} prefetch><span>{child.label}</span></Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        );
    };

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="pt-4 pb-5">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={adminUrl()} prefetch>
                                <BrandMark />
                                <span className="truncate font-semibold">{sunrice.brand}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-5">
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={path === adminUrl()} tooltip="Dashboard">
                                <Link href={adminUrl()} prefetch><LayoutGrid /><span>Dashboard</span></Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                {navigation.map((group) => (
                    <SidebarGroup key={group.group ?? ''} className="px-2 py-0">
                        {group.group && (
                            <SidebarGroupLabel className="mb-1 text-[10px] font-semibold tracking-[0.14em] uppercase">{group.group}</SidebarGroupLabel>
                        )}
                        <SidebarMenu>{group.items.map(item)}</SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60 py-3">
                {auth.user && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton size="lg" tooltip={auth.user.name ?? auth.user.email ?? ''}>
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                            {(auth.user.name ?? auth.user.email ?? '?').slice(0, 1).toUpperCase()}
                                        </span>
                                        <span className="grid min-w-0 text-left leading-tight">
                                            <span className="truncate font-medium">{auth.user.name ?? auth.user.email}</span>
                                            {auth.user.name && <span className="truncate text-xs text-muted-foreground">{auth.user.email}</span>}
                                        </span>
                                        <ChevronsUpDown className="ml-auto size-4" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent side="top" align="start" className="w-56">
                                    <DropdownMenuItem onSelect={() => router.post(adminUrl('logout'))}>
                                        <LogOut />
                                        Log out
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
