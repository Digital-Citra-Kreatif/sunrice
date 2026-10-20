import * as React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface PageHeaderProps {
    title: React.ReactNode;
    description?: React.ReactNode;
    /** A button back to the list this page belongs to. */
    back?: { href: string; label: string };
    /** Buttons on the right. */
    actions?: React.ReactNode;
    /** Beside the title: a status badge, a switcher, "Unsaved changes". */
    children?: React.ReactNode;
    className?: string;
}

/** The block at the top of every admin page: the title (sunrice-page-title), what's beside it, and its actions. */
export function PageHeader({ title, description, back, actions, children, className }: PageHeaderProps) {
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
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
