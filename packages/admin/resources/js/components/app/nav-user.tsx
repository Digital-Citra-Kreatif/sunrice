import { router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Monitor, Moon, Sun } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { adminUrl } from '@/lib/admin-url';
import { initials } from '@/lib/branding';
import type { SharedProps } from '@/types';

type User = NonNullable<SharedProps['auth']['user']>;

function UserInfo({ user }: { user: User }) {
    return (
        <>
            <Avatar className="size-8">
                <AvatarFallback className="bg-sidebar-accent text-xs font-medium text-sidebar-accent-foreground">{initials(user.name, user.email)}</AvatarFallback>
            </Avatar>
            <span className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name ?? user.email}</span>
                {user.name && <span className="truncate text-xs text-muted-foreground">{user.email}</span>}
            </span>
        </>
    );
}

/** The signed-in user at the foot of the sidebar: appearance (light, dark, system) and logging out. */
export function NavUser() {
    const { auth } = usePage<SharedProps>().props;
    const { isMobile, state } = useSidebar();
    const { appearance, updateAppearance } = useAppearance();
    const user = auth.user;
    if (!user) return null;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" tooltip={user.name ?? user.email ?? 'Account'}
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <UserInfo user={user} />
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'top'} align="end" sideOffset={4}>
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5"><UserInfo user={user} /></div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">Appearance</DropdownMenuLabel>
                            <DropdownMenuRadioGroup value={appearance} onValueChange={(value) => updateAppearance(value as Appearance)}>
                                <DropdownMenuRadioItem value="light"><Sun /> Light</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="dark"><Moon /> Dark</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="system"><Monitor /> System</DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => router.post(adminUrl('logout'))}>
                            <LogOut /> Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
