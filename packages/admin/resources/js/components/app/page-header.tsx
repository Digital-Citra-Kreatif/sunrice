import * as React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MoreVertical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { menuGroups, type PageHeaderMenuItem } from '@/lib/page-header-menu';
import { cn } from '@/lib/utils';

export type { PageHeaderMenuItem };

export interface PageHeaderProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    /** A button back to the list this page belongs to. */
    back?: { href: string; label: string };
    /** Buttons on the right, before the primary action and the menu. */
    actions?: React.ReactNode;
    /** The page's main action ("New entry"), the one prominent button. */
    primary?: React.ReactNode;
    /** Secondary actions and links (Export CSV, settings, blueprint…), in a "⋮" menu after the primary action. */
    menu?: PageHeaderMenuItem[];
    /** Beside the title: a status badge, a switcher, "Unsaved changes". */
    children?: React.ReactNode;
    className?: string;
}

/**
 * The block at the top of every admin page: the title (sunrice-page-title), what's beside it, and its actions:
 * one primary button and the rest in a "⋮" menu (table controls such as Columns belong to the table's toolbar).
 */
export function PageHeader({ title, description, back, actions, primary, menu, children, className }: PageHeaderProps) {
    const hasMenu = menu !== undefined && menu.length > 0;

    return (
        <div className={cn('flex flex-wrap items-center justify-between gap-4', className)}>
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                {back && (
                    <Button variant="outline" size="icon" className="size-8 shrink-0" asChild>
                        <Link href={back.href} aria-label={back.label}><ArrowLeft /></Link>
                    </Button>
                )}
                <div className="min-w-0">
                    <h1 className="sunrice-page-title truncate">{title}</h1>
                    {description && <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">{description}</p>}
                </div>
                {children}
            </div>
            {(actions || primary || hasMenu) && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                    {primary}
                    {hasMenu && <PageHeaderMenu items={menu} />}
                </div>
            )}
        </div>
    );
}

function PageHeaderMenu({ items }: { items: PageHeaderMenuItem[] }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label={t('More actions')} title={t('More actions')}><MoreVertical /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56">
                {menuGroups(items).map(([group, list], index) => (
                    <DropdownMenuGroup key={group || `group-${index}`}>
                        {index > 0 && <DropdownMenuSeparator />}
                        {group !== '' && <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">{group}</DropdownMenuLabel>}
                        {list.map((item, i) => {
                            const variant = item.destructive ? 'destructive' : 'default';
                            const content = <>{item.icon}<span className="truncate">{item.label}</span>{item.external && <ExternalLink className="ml-auto" />}</>;
                            if (!item.href) {
                                return <DropdownMenuItem key={i} variant={variant} onSelect={item.onSelect}>{content}</DropdownMenuItem>;
                            }
                            return (
                                <DropdownMenuItem key={i} variant={variant} asChild>
                                    {item.external || item.download
                                        ? <a href={item.href} {...(item.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>{content}</a>
                                        : <Link href={item.href}>{content}</Link>}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuGroup>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
