import { Head, usePage } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import type { SharedProps } from '@/types';

export default function Dashboard() {
    const { auth, sunrice } = usePage<SharedProps>().props;
    useBreadcrumbs([{ label: 'Dashboard' }]);

    return (
        <>
            <Head title="Dashboard" />
            <PageHeader title={`Welcome${auth.user?.name ? `, ${auth.user.name}` : ''}`} description={`This is the ${sunrice.brand} admin panel.`} />
        </>
    );
}
