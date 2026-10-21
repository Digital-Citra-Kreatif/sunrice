import * as React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('forgot-password'));
    };

    return (
        <AuthLayout title={t('Forgot password')} description={t('Enter your email address and we\'ll send you a link to choose a new password.')}>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">{t('Email address')}</Label>
                    <Input id="email" type="email" autoComplete="email" autoFocus required value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} />
                    <InputError message={form.errors.email} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('Email the reset link')}
                </Button>
                <p className="text-center text-sm text-muted-foreground">
                    <Link href={adminUrl('login')} className="underline-offset-4 hover:text-foreground hover:underline">{t('Back to log in')}</Link>
                </p>
            </form>
        </AuthLayout>
    );
}

ForgotPassword.bare = true;
