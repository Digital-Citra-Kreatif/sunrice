import * as React from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';

interface Props {
    /** The address the code went to, partly hidden (j•••@example.com). */
    email: string;
    /** Seconds until another code may be sent. */
    resendIn: number;
    /** How long a code works. */
    minutes: number;
}

export default function TwoFactor({ email, resendIn, minutes }: Props) {
    const form = useForm({ code: '' });
    const [wait, setWait] = React.useState(resendIn);
    const [resending, setResending] = React.useState(false);

    React.useEffect(() => setWait(resendIn), [resendIn]);
    React.useEffect(() => {
        if (wait <= 0) return;
        const timer = window.setTimeout(() => setWait((w) => w - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [wait]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('login/code'), { onError: () => form.reset('code') });
    };

    const resend = () => router.post(adminUrl('login/code/resend'), {}, {
        onStart: () => setResending(true),
        onFinish: () => setResending(false),
    });

    return (
        <AuthLayout title={t('Check your email')} description={t('We sent a 6-digit code to :email. It works for :minutes minutes.', { email, minutes })}>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="code">{t('Login code')}</Label>
                    <Input id="code" inputMode="numeric" autoComplete="one-time-code" autoFocus required maxLength={20}
                        className="text-center font-mono text-lg tracking-[0.5em]" value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)} aria-invalid={!!form.errors.code} />
                    <InputError message={form.errors.code} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('Log in')}
                </Button>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <Button type="button" variant="link" className="h-auto p-0 text-muted-foreground" disabled={wait > 0 || resending} onClick={resend}>
                        {wait > 0 ? t('Send a new code in :time', { time: `${wait}s` }) : t('Send a new code')}
                    </Button>
                    <Link href={adminUrl('login')} className="underline-offset-4 hover:text-foreground hover:underline">{t('Back to log in')}</Link>
                </div>
            </form>
        </AuthLayout>
    );
}

TwoFactor.bare = true;
