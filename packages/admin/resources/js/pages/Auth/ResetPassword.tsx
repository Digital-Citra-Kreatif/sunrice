import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('reset-password'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title="Choose a new password">
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input id="email" type="email" autoComplete="email" value={form.data.email} readOnly className="bg-muted/50" />
                    <InputError message={form.errors.email} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">New password</Label>
                    <Input id="password" type="password" autoComplete="new-password" autoFocus required value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} />
                    <InputError message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm the new password</Label>
                    <Input id="password_confirmation" type="password" autoComplete="new-password" required value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    Save the new password
                </Button>
            </form>
        </AuthLayout>
    );
}

ResetPassword.bare = true;
