import * as React from 'react';
import { router, useForm } from '@inertiajs/react';
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

/** After signing in with a weak password: a stronger one, typed twice, before anything else (RequireStrongPassword). */
export default function StrongerPassword({ email, passwordRules = null }: { email: string | null; passwordRules?: PasswordRules | null }) {
    const form = useForm({ password: '', password_confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(adminUrl('stronger-password'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title={t('Choose a stronger password')}
            description={t('Your password doesn\'t meet this site\'s password rules any more. Choose a new one to go on.')}>
            <form onSubmit={submit} className="grid gap-6">
                {/* For password managers: whose password this is. */}
                <input type="email" name="email" autoComplete="username" value={email ?? ''} readOnly hidden />
                <div className="grid gap-2">
                    <Label htmlFor="password">{t('New password')}</Label>
                    <Input id="password" type="password" autoComplete="new-password" autoFocus required value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password}
                        aria-describedby={passwordRules ? 'password-rules' : undefined} />
                    <PasswordChecklist id="password-rules" rules={passwordRules} value={form.data.password} />
                    <InputError message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">{t('Confirm the new password')}</Label>
                    <Input id="password_confirmation" type="password" autoComplete="new-password" required value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {t('Save the new password')}
                </Button>
                <Button type="button" variant="ghost" className="w-full" onClick={() => router.post(adminUrl('logout'))}>
                    {t('Log out')}
                </Button>
            </form>
        </AuthLayout>
    );
}

StrongerPassword.bare = true;
