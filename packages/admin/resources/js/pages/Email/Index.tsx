import * as React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Send } from 'lucide-react';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';

interface Props {
    mailer: string;
    transport: string;
    server: string | null;
    from: { address: string | null; name: string | null };
    sends: boolean;
    to: string | null;
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-1 py-2.5 sm:grid-cols-[10rem_1fr] sm:gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words text-sm">{children}</dd>
        </div>
    );
}

export default function EmailIndex({ mailer, transport, server, from, sends, to }: Props) {
    useBreadcrumbs([{ label: 'Email' }]);
    const form = useForm({ to: to ?? '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('email'), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Email" />
            <div className="mx-auto grid max-w-3xl gap-6">
                <h1 className="text-2xl font-medium tracking-tight">Email</h1>
                <Card>
                    <CardHeader>
                        <CardTitle>How email is sent</CardTitle>
                        <CardDescription>From the app's mail settings (config/mail.php and .env).</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y">
                            <Row label="Mailer">
                                {mailer}
                                {transport !== mailer && <span className="text-muted-foreground"> ({transport})</span>}
                            </Row>
                            {server && <Row label="Server">{server}</Row>}
                            <Row label="Sent from">{from.address ? `${from.name ? `${from.name} ` : ''}<${from.address}>` : <span className="text-destructive">Not set (MAIL_FROM_ADDRESS)</span>}</Row>
                        </dl>
                        {!sends && (
                            <p className="mt-4 rounded-md bg-muted px-3 py-2 text-sm">
                                This mailer keeps messages instead of sending them (they go to the log). Set MAIL_MAILER to send real email: password resets, sign-in codes and alerts depend on it.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Send a test email</CardTitle>
                        <CardDescription>Check that messages arrive, and don't land in spam.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="to" className="sr-only">Send to</Label>
                                <Input id="to" type="email" required value={form.data.to} onChange={(event) => form.setData('to', event.target.value)} aria-invalid={form.errors.to !== undefined} />
                                <InputError message={form.errors.to} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                                Send
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
