import * as React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { useBranding } from '@/components/app/app-logo';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';

export default function Login() {
    const { name } = useBranding();
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('login'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title={t('Log in to :name', { name })} description={t('Enter your email and password to continue.')}>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">{t('Email address')}</Label>
                    <Input id="email" type="email" autoComplete="email" autoFocus required value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} />
                    <InputError message={form.errors.email} />
                </div>
                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Link href={adminUrl('forgot-password')} className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                            {t('Forgot password?')}
                        </Link>
                    </div>
                    <Input id="password" type="password" autoComplete="current-password" required value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} />
                    <InputError message={form.errors.password} />
                </div>
                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={form.data.remember} onCheckedChange={(checked) => form.setData('remember', checked === true)} />
                    <Label htmlFor="remember" className="font-normal">{t('Remember me')}</Label>
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('Log in')}
                </Button>
            </form>
        </AuthLayout>
    );
}

Login.bare = true;
