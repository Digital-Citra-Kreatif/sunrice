import { Head } from '@inertiajs/react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { HelpReader, type HelpPageProps } from '@/components/app/help-reader';
import { PageHeader } from '@/components/app/page-header';
import { adminUrl } from '@/lib/admin-url';

/** Help → Developer docs: the Markdown docs the panel and its plugins register (Panel::docs()). */
export default function HelpDocs(props: HelpPageProps) {
    useBreadcrumbs([{ label: 'Help' }, { label: 'Developer docs', href: adminUrl('docs') }, ...(props.title ? [{ label: props.title }] : [])]);

    return (
        <>
            <Head title={props.title ? `${props.title} · Developer docs` : 'Developer docs'} />
            <div className="flex flex-col gap-6">
                <PageHeader title="Developer docs" description="How the panel and its plugins work, for the people who build and run this site." />
                <HelpReader
                    kind="docs"
                    {...props}
                    labels={{ pages: 'Pages', onThisPage: 'On this page', previous: 'Previous', next: 'Next', empty: 'No developer docs are installed.' }}
                />
            </div>
        </>
    );
}
