import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, LayoutGrid } from 'lucide-react';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, SidebarRail, useSidebar,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AppLogo } from '@/components/app/app-logo';
import { NavUser } from '@/components/app/nav-user';
import { navIcon } from '@/components/app/nav-icon';
import { adminUrl } from '@/lib/admin-url';
import { FOLDED_KEY, parseFolded, withFolded } from '@/lib/folded-groups';
import type { NavItem, SharedProps } from '@/types';

/** Whether the current URL is this link's page or one below it. */
function isCurrent(currentPath: string, url: string): boolean {
    const href = adminUrl(url);
    return currentPath === href || (url !== '' && currentPath.startsWith(`${href}/`));
}

export function AppSidebar() {
    const { navigation, sunrice } = usePage<SharedProps>().props;
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
                        <SidebarMenuButton size="lg" asChild tooltip={sunrice.branding.name}>
                            <Link href={adminUrl()} prefetch><AppLogo /></Link>
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

                {navigation.map((group) => group.group ? (
                    <NavGroup key={group.group} label={group.group}
                        current={group.items.some((entry) => isCurrent(path, entry.url) || entry.children.some((child) => isCurrent(path, child.url)))}>
                        {group.items.map(item)}
                    </NavGroup>
                ) : (
                    <SidebarGroup key="" className="px-2 py-0">
                        <SidebarMenu>{group.items.map(item)}</SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60 py-3">
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

function foldedGroups(): string[] {
    try {
        return parseFolded(window.localStorage.getItem(FOLDED_KEY));
    } catch {
        return [];
    }
}

/**
 * A titled group of the sidebar that folds away when its title is clicked,
 * remembered per browser. The group holding the current page stays open.
 */
function NavGroup({ label, current, children }: { label: string; current: boolean; children: React.ReactNode }) {
    const [open, setOpen] = React.useState(() => current || !foldedGroups().includes(label));
    // Shrunk to icons, the titles are hidden: every group shows its icons.
    const { state } = useSidebar();
    const toggle = (next: boolean) => {
        setOpen(next);
        try {
            window.localStorage.setItem(FOLDED_KEY, JSON.stringify(withFolded(foldedGroups(), label, next)));
        } catch {
            // Private mode: it just isn't remembered.
        }
    };

    return (
        <Collapsible open={open || state === 'collapsed'} onOpenChange={toggle} className="group/navgroup">
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild className="mb-1 text-[10px] font-semibold tracking-[0.14em] uppercase">
                    <CollapsibleTrigger className="flex w-full cursor-pointer items-center hover:text-sidebar-foreground">
                        {label}
                        <ChevronRight className="ml-auto size-3.5 transition-transform group-data-[state=open]/navgroup:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <SidebarMenu>{children}</SidebarMenu>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
