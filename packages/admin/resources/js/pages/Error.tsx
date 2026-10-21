import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** What each status says (the panel's ErrorPages sends 403, 404, 419 and 500), in the user's language. */
export function errorMessage(status: number): { title: string; body: string } {
    switch (status) {
        case 403: return { title: t('Not allowed'), body: t('You don\'t have permission to see this page. Ask an administrator if you need it.') };
        case 404: return { title: t('Page not found'), body: t('There is nothing at this address. It may have moved, or the link is wrong.') };
        case 419: return { title: t('Page expired'), body: t('The page was open too long. Reload it and try again.') };
        default: return { title: t('Something went wrong'), body: t('An unexpected error happened. It was recorded; try again in a moment.') };
    }
}

/** An error page under the panel's path, in the admin layout when someone is signed in. */
export default function ErrorPage({ status }: { status: number }) {
    const message = errorMessage(status);
    useBreadcrumbs([{ label: message.title }]);

    return (
        <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
            <Head title={message.title} />
            <div className="text-6xl font-semibold tabular-nums text-muted-foreground">{status}</div>
            <h1 className="sunrice-page-title">{message.title}</h1>
            <p className="max-w-md text-sm text-muted-foreground">{message.body}</p>
            <div className="flex gap-2">
                {status === 419 ? (
                    <Button variant="outline" onClick={() => window.location.reload()}>{t('Reload')}</Button>
                ) : (
                    <Button asChild variant="outline"><Link href={adminUrl()}>{t('Back to the dashboard')}</Link></Button>
                )}
            </div>
        </div>
    );
}

/** The admin layout needs a signed-in user; without one, the page stands alone. */
function ErrorLayout({ children }: { children: React.ReactNode }) {
    const { auth } = usePage<Partial<SharedProps>>().props;

    return auth?.user ? <AppLayout>{children}</AppLayout> : <main className="flex min-h-svh items-center justify-center p-6">{children}</main>;
}

ErrorPage.layout = (page: React.ReactNode) => <ErrorLayout>{page}</ErrorLayout>;
