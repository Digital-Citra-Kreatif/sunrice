import * as React from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useBreadcrumbs } from '@/components/app/breadcrumbs';
import { PageHeader } from '@/components/app/page-header';
import { InputError } from '@/components/app/input-error';
import { useConfirm } from '@/components/resources/confirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { adminUrl } from '@/lib/admin-url';
import { copyText } from '@/lib/clipboard';
import { panelLocale, t } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface Token {
    id: number;
    name: string;
    readOnly: boolean;
    createdAt: string | null;
    lastUsedAt: string | null;
    lastUsedIp: string | null;
    expiresAt: string | null;
    expired: boolean;
}

interface Props {
    endpoint: string;
    tokens: Token[];
    created: { name: string; token: string } | null;
    /** What a full token could do as this person. */
    risks?: { superAdmin: boolean; privileged: string[] };
}

const NEVER = 'never';
const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString(panelLocale(), { dateStyle: 'medium', timeStyle: 'short' }) : '—');

/** The user's AI access tokens, and how to connect an AI agent. */
export default function AiIndex({ endpoint, tokens, created, risks = { superAdmin: false, privileged: [] } }: Props) {
    useBreadcrumbs([{ label: t('AI access') }]);
    const [confirm, confirmDialog] = useConfirm();
    const [creating, setCreating] = React.useState(false);
    const form = useForm<{ name: string; read_only: boolean; expires_in_days: string }>({ name: '', read_only: false, expires_in_days: '90' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, expires_in_days: data.expires_in_days === NEVER ? null : Number(data.expires_in_days) }));
        form.post(adminUrl('ai/tokens'), { preserveScroll: true, onSuccess: () => { setCreating(false); form.reset(); } });
    };
    const { sunrice } = usePage<SharedProps>().props;
    const copy = (text: string) => copyText(text).then(() => toast.success(t('Copied.')), () => toast.error(t('Copying failed: select the text and copy it yourself.')));

    const token = created?.token ?? 'YOUR_TOKEN';
    const server = sunrice.branding.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'panel';
    const claudeCode = `claude mcp add --transport http ${server} ${endpoint} --header "Authorization: Bearer ${token}"`;
    const json = JSON.stringify({ mcpServers: { [server]: { type: 'http', url: endpoint, headers: { Authorization: `Bearer ${token}` } } } }, null, 2);

    return (
        <div className="flex max-w-5xl flex-col gap-6">
            <Head title={t('AI access')} />
            {confirmDialog}
            <PageHeader
                title={t('AI access')}
                description={t('Connect an AI agent (Claude, ChatGPT, Cursor …) to this site through MCP. It works in the panel as you, always with your permissions.')}
                actions={<Button onClick={() => setCreating(true)}><Plus />{t('New token')}</Button>}
            />

            <Card>
                <CardHeader>
                    <CardTitle>{t('Connect an agent')}</CardTitle>
                    <CardDescription>
                        {created
                            ? t('The server address and the header carrying your token. Most agents accept one of these.')
                            : t('The server address and the header carrying your token (create a token below, then paste it in place of YOUR_TOKEN). Most agents accept one of these.')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <CodeBlock label={t('Server URL')} code={endpoint} onCopy={copy} />
                    <CodeBlock label="Claude Code" code={claudeCode} onCopy={copy} />
                    <CodeBlock label={t('Claude Desktop, Cursor, VS Code … (JSON config)')} code={json} onCopy={copy} />
                    <p className="text-xs text-muted-foreground">
                        {t('Read-only tokens can look but not change anything; they still read everything you can see, personal data included (people\'s names and emails, messages sent through forms).')}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {withCode(t('For an agent running on the server itself: :command, with :variable set to the email of the user it acts as. See Help → Developer docs → AI access.'), { command: 'php artisan mcp:start sunrice', variable: 'SUNRICE_AI_USER' })}
                    </p>
                </CardContent>
            </Card>

            {created && (
                <Card className="border-primary">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><KeyRound className="size-4" />{t('Your new token “:name”', { name: created.name })}</CardTitle>
                        <CardDescription>{t('Copy it now: it won\'t be shown again. Anyone with it can work in the panel as you.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="flex gap-2">
                        <Input readOnly value={created.token} className="font-mono" aria-label={t('The new token')} onFocus={(e) => e.target.select()} />
                        <Button variant="outline" onClick={() => copy(created.token)}><Copy />{t('Copy')}</Button>
                    </CardContent>
                </Card>
            )}

            <h2 className="mt-2 text-base font-medium">{t('Your access tokens')}</h2>
            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('Name')}</TableHead>
                            <TableHead>{t('Last used')}</TableHead>
                            <TableHead>{t('Expires')}</TableHead>
                            <TableHead className="w-12"><span className="sr-only">{t('Revoke')}</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tokens.length === 0 && (
                            <TableRow><TableCell colSpan={4} className="py-10 text-center text-muted-foreground">{t('No tokens yet.')}</TableCell></TableRow>
                        )}
                        {tokens.map((token) => (
                            <TableRow key={token.id}>
                                <TableCell>
                                    <span className="font-medium">{token.name}</span>{' '}
                                    {token.readOnly && <Badge variant="secondary">{t('Read-only')}</Badge>}{' '}
                                    {token.expired && <Badge variant="destructive">{t('Expired')}</Badge>}
                                    <div className="text-xs text-muted-foreground">{t('Created :date', { date: when(token.createdAt) })}</div>
                                </TableCell>
                                <TableCell className="text-sm">{when(token.lastUsedAt)}{token.lastUsedIp && <div className="text-xs text-muted-foreground">{t('from :ip', { ip: token.lastUsedIp })}</div>}</TableCell>
                                <TableCell className="text-sm">{token.expiresAt ? when(token.expiresAt) : t('Never')}</TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="icon" className="text-destructive" aria-label={t('Revoke :name', { name: token.name })}
                                        onClick={() => confirm(t('Revoke ":name"? Agents using it stop working at once.', { name: token.name }), () => router.delete(adminUrl(`ai/tokens/${token.id}`), { preserveScroll: true }), true)}>
                                        <Trash2 />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>


            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>{t('New AI access token')}</DialogTitle>
                            <DialogDescription>{t('Name it after the agent or computer that uses it, so you know what to revoke.')}</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="token-name">{t('Name')}</Label>
                            <Input id="token-name" required maxLength={100} placeholder={t('Claude on my laptop')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox id="token-read-only" checked={form.data.read_only} onCheckedChange={(checked) => form.setData('read_only', checked === true)} />
                            <Label htmlFor="token-read-only" className="font-normal">{t('Read-only: the agent can look, but not change anything')}</Label>
                        </div>
                        {!form.data.read_only && (risks.superAdmin || risks.privileged.length > 0) && (
                            <div className="rounded-md border border-amber-500/40 bg-amber-500/5 p-3 text-sm" role="note">
                                <p>{risks.superAdmin
                                    ? t('You are a super admin: a full token can do everything in the panel, delete included, as you. Among it:')
                                    : t('A full token can do everything you can, as you. Among it:')}</p>
                                <ul className="mt-1 list-disc pl-5">
                                    {risks.privileged.slice(0, 6).map((label) => <li key={label}>{label}</li>)}
                                    {risks.privileged.length > 6 && <li>{t('and :count more', { count: risks.privileged.length - 6 })}</li>}
                                </ul>
                                <p className="mt-1 text-muted-foreground">{t('Unless the agent must change things, make it read-only; otherwise give it a short expiry.')}</p>
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="token-expires">{t('Expires')}</Label>
                            <Select value={form.data.expires_in_days} onValueChange={(value) => form.setData('expires_in_days', value)}>
                                <SelectTrigger id="token-expires" className="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NEVER}>{t('Never')}</SelectItem>
                                    <SelectItem value="30">{t('In 30 days')}</SelectItem>
                                    <SelectItem value="90">{t('In 90 days')}</SelectItem>
                                    <SelectItem value="365">{t('In a year')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreating(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={form.processing}>{t('Create token')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

/** A translated sentence with :placeholders shown as code. */
function withCode(text: string, codes: Record<string, string>): React.ReactNode {
    return text.split(/(:[a-z_]+)/).map((part, i) => {
        const code = codes[part.slice(1)];
        return part.startsWith(':') && code !== undefined ? <code key={i} className="font-mono">{code}</code> : part;
    });
}

/** A labelled block of text to copy: an address, a command, a config. */
function CodeBlock({ label, code, onCopy }: { label: string; code: string; onCopy: (text: string) => void }) {
    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-2">
                <Label>{label}</Label>
                <Button type="button" variant="ghost" size="sm" onClick={() => onCopy(code)}><Copy />{t('Copy')}</Button>
            </div>
            <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2.5 font-mono text-xs leading-relaxed"><code>{code}</code></pre>
        </div>
    );
}
