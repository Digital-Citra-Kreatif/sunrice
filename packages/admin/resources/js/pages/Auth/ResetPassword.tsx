import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { InputError } from '@/components/app/input-error';
import { PasswordChecklist } from '@/components/app/password-checklist';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';
import { t } from '@/lib/i18n';
import type { PasswordRules } from '@/lib/password-rules';

/** The reset page; from an invitation (`invited`), a new user chooses their first password. */
export default function ResetPassword({ token, email, invited = false, passwordRules = null }: { token: string; email: string; invited?: boolean; passwordRules?: PasswordRules | null }) {
    const form = useForm({ token, email, password: '', password_confirmation: '', invited });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('reset-password'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title={invited ? t('Choose your password') : t('Choose a new password')}>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">{t('Email address')}</Label>
                    <Input id="email" type="email" autoComplete="email" value={form.data.email} readOnly className="bg-muted/50" />
                    <InputError message={form.errors.email} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">{invited ? t('Password') : t('New password')}</Label>
                    <Input id="password" type="password" autoComplete="new-password" autoFocus required value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password}
                        aria-describedby={passwordRules ? 'password-rules' : undefined} />
                    <PasswordChecklist id="password-rules" rules={passwordRules} value={form.data.password} />
                    <InputError message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">{invited ? t('Confirm the password') : t('Confirm the new password')}</Label>
                    <Input id="password_confirmation" type="password" autoComplete="new-password" required value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {invited ? t('Save my password') : t('Save the new password')}
                </Button>
            </form>
        </AuthLayout>
    );
}

ResetPassword.bare = true;
