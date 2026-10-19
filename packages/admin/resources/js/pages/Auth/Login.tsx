import * as React from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { InputError } from '@/components/app/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminUrl } from '@/lib/admin-url';
import type { SharedProps } from '@/types';

export default function Login() {
    const { login } = usePage<SharedProps>().props.sunrice;
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adminUrl('login'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title={login.headline ?? 'Log in'} description={login.text ?? 'Sign in to the admin panel.'}>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input id="email" type="email" autoComplete="email" autoFocus required value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)} aria-invalid={!!form.errors.email} />
                    <InputError message={form.errors.email} />
                </div>
                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        <Link href={adminUrl('forgot-password')} className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                            Forgot password?
                        </Link>
                    </div>
                    <Input id="password" type="password" autoComplete="current-password" required value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)} aria-invalid={!!form.errors.password} />
                    <InputError message={form.errors.password} />
                </div>
                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={form.data.remember} onCheckedChange={(checked) => form.setData('remember', checked === true)} />
                    <Label htmlFor="remember" className="font-normal">Remember me</Label>
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    Log in
                </Button>
            </form>
        </AuthLayout>
    );
}

Login.bare = true;
