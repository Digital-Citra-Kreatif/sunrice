import { Head, usePage } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import type { SharedProps } from '@/types';

export default function Dashboard() {
    const { auth, sunrice } = usePage<SharedProps>().props;
    useBreadcrumbs([{ label: 'Dashboard' }]);

    return (
        <>
            <Head title="Dashboard" />
            <h1 className="text-2xl font-medium tracking-tight">Welcome{auth.user?.name ? `, ${auth.user.name}` : ''}</h1>
            <p className="mt-2 text-muted-foreground">This is the {sunrice.brand} admin panel.</p>
        </>
    );
}
