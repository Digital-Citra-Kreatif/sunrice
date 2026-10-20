import * as React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import { adminUrl } from '@/lib/admin-url';
import type { SharedProps } from '@/types';

/** What each status says (the panel's ErrorPages sends 403, 404, 419 and 500). */
export const ERROR_MESSAGES: Record<number, { title: string; body: string }> = {
    403: { title: 'Not allowed', body: 'You don\'t have permission to see this page. Ask an administrator if you need it.' },
    404: { title: 'Page not found', body: 'There is nothing at this address. It may have moved, or the link is wrong.' },
    419: { title: 'Page expired', body: 'The page was open too long. Reload it and try again.' },
    500: { title: 'Something went wrong', body: 'An unexpected error happened. It was recorded; try again in a moment.' },
};

export function errorMessage(status: number): { title: string; body: string } {
    return ERROR_MESSAGES[status] ?? ERROR_MESSAGES[500]!;
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
                    <Button variant="outline" onClick={() => window.location.reload()}>Reload</Button>
                ) : (
                    <Button asChild variant="outline"><Link href={adminUrl()}>Back to the dashboard</Link></Button>
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
