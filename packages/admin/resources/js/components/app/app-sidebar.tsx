import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, LayoutGrid } from 'lucide-react';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, SidebarRail, useSidebar,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { AppLogo } from '@/components/app/app-logo';
import { NavUser } from '@/components/app/nav-user';
import { navIcon } from '@/components/app/nav-icon';
import { adminUrl } from '@/lib/admin-url';
import { FOLDED_KEY, parseFolded, withFolded } from '@/lib/folded-groups';
import { matchNavigation } from '@/lib/nav-match';
import type { NavItem, SharedProps } from '@/types';
import { t } from '@/lib/i18n';


export function AppSidebar() {
    const { navigation, sunrice } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0] ?? '';
    const { isMobile, setOpenMobile, state } = useSidebar();
    // Folded to icons (desktop): a submenu can't unfold under its icon, so it opens beside it.
    const iconsOnly = state === 'collapsed' && !isMobile;
    // Only the closest link is current: cms/collections/blog/… is Blog's, not Collections'.
    const match = matchNavigation(navigation, path, adminUrl);
    const isCurrent = (url: string) => match !== null && match.link.url === url;

    // On phones the sidebar is a flyout: close it when a visit starts.
    React.useEffect(() => {
        if (!isMobile) return;
        return router.on('start', () => setOpenMobile(false));
    }, [isMobile, setOpenMobile]);

    const item = (entry: NavItem) => {
        const Icon = navIcon(entry.icon);
        const open = entry.children.some((child) => isCurrent(child.url));
        if (entry.children.length === 0) {
            return (
                <SidebarMenuItem key={entry.key}>
                    <SidebarMenuButton asChild isActive={isCurrent(entry.url)} tooltip={entry.label}>
                        <Link href={adminUrl(entry.url)} prefetch><Icon /><span>{entry.label}</span></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            );
        }

        const links = entry.children.some((child) => child.url === entry.url)
            ? entry.children
            : [{ key: `${entry.key}:self`, label: entry.label, url: entry.url }, ...entry.children];

        if (iconsOnly) {
            return (
                <SidebarMenuItem key={entry.key}>
                    <NavFlyout label={entry.label} active={isCurrent(entry.url) || open} icon={<Icon />}
                        links={links.map((child) => ({ ...child, current: adminUrl(child.url) === path }))} />
                </SidebarMenuItem>
            );
        }

        return (
            <Collapsible key={entry.key} asChild defaultOpen={open} className="group/submenu">
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton tooltip={entry.label} isActive={isCurrent(entry.url) && !open}>
                            <Icon />
                            <span>{entry.label}</span>
                            <ChevronRight className="ml-auto transition-transform duration-200 ease-out group-data-[state=open]/submenu:rotate-90 motion-reduce:transition-none" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="sunrice-collapsible">
                        <SidebarMenuSub>
                            {links.map((child) => (
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
                            <SidebarMenuButton asChild isActive={path === adminUrl()} tooltip={t('Dashboard')}>
                                <Link href={adminUrl()} prefetch><LayoutGrid /><span>{t('Dashboard')}</span></Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                {navigation.map((group) => group.group ? (
                    <NavGroup key={group.group} label={group.group}
                        current={group.items.some((entry) => isCurrent(entry.url) || entry.children.some((child) => isCurrent(child.url)))}>
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
/**
 * A menu entry with a submenu while the sidebar is folded to icons: its icon opens a
 * menu beside it with the entry's own page and its subpages (keyboard and screen
 * readers get a menu button; the tooltip still names it on hover).
 */
export function NavFlyout({ label, active, icon, links }: {
    label: string;
    active: boolean;
    icon: React.ReactNode;
    links: Array<{ key: string; label: string; url: string; current: boolean }>;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <SidebarMenuButton tooltip={label} isActive={active} aria-label={label}>
                    {icon}
                    <span>{label}</span>
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start" sideOffset={8} className="min-w-48">
                <DropdownMenuLabel>{label}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    {links.map((link) => (
                        <DropdownMenuItem key={link.key} asChild className={link.current ? 'bg-accent font-medium' : undefined}>
                            <Link href={adminUrl(link.url)} prefetch aria-current={link.current ? 'page' : undefined}>{link.label}</Link>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

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
                        <ChevronRight aria-hidden="true" className="ml-auto size-3.5 transition-transform duration-200 ease-out group-data-[state=open]/navgroup:rotate-90 motion-reduce:transition-none" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent className="sunrice-collapsible">
                    <SidebarMenu>{children}</SidebarMenu>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
