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
}

const NEVER = 'never';
const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—');

/** The user's AI access tokens, and how to connect an AI agent. */
export default function AiIndex({ endpoint, tokens, created }: Props) {
    useBreadcrumbs([{ label: 'AI access' }]);
    const [confirm, confirmDialog] = useConfirm();
    const [creating, setCreating] = React.useState(false);
    const form = useForm<{ name: string; read_only: boolean; expires_in_days: string }>({ name: '', read_only: false, expires_in_days: '90' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, expires_in_days: data.expires_in_days === NEVER ? null : Number(data.expires_in_days) }));
        form.post(adminUrl('ai/tokens'), { preserveScroll: true, onSuccess: () => { setCreating(false); form.reset(); } });
    };
    const { sunrice } = usePage<SharedProps>().props;
    const copy = (text: string) => copyText(text).then(() => toast.success('Copied.'), () => toast.error('Copying failed: select the text and copy it yourself.'));

    const token = created?.token ?? 'YOUR_TOKEN';
    const server = sunrice.branding.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'panel';
    const claudeCode = `claude mcp add --transport http ${server} ${endpoint} --header "Authorization: Bearer ${token}"`;
    const json = JSON.stringify({ mcpServers: { [server]: { type: 'http', url: endpoint, headers: { Authorization: `Bearer ${token}` } } } }, null, 2);

    return (
        <div className="flex max-w-5xl flex-col gap-6">
            <Head title="AI access" />
            {confirmDialog}
            <PageHeader
                title="AI access"
                description="Connect an AI agent (Claude, ChatGPT, Cursor …) to this site through MCP. It works in the panel as you, always with your permissions."
                actions={<Button onClick={() => setCreating(true)}><Plus />New token</Button>}
            />

            <Card>
                <CardHeader>
                    <CardTitle>Connect an agent</CardTitle>
                    <CardDescription>
                        The server address and the header carrying your token{created ? '' : ' (create a token below, then paste it in place of YOUR_TOKEN)'}. Most agents accept one of these.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <CodeBlock label="Server URL" code={endpoint} onCopy={copy} />
                    <CodeBlock label="Claude Code" code={claudeCode} onCopy={copy} />
                    <CodeBlock label="Claude Desktop, Cursor, VS Code … (JSON config)" code={json} onCopy={copy} />
                    <p className="text-xs text-muted-foreground">
                        For an agent running on the server itself: <code className="font-mono">php artisan mcp:start sunrice</code>, with <code className="font-mono">SUNRICE_AI_USER</code> set to the email of the user it acts as. See Help → Developer docs → AI access.
                    </p>
                </CardContent>
            </Card>

            {created && (
                <Card className="border-primary">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><KeyRound className="size-4" />Your new token “{created.name}”</CardTitle>
                        <CardDescription>Copy it now: it won't be shown again. Anyone with it can work in the panel as you.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex gap-2">
                        <Input readOnly value={created.token} className="font-mono" aria-label="The new token" onFocus={(e) => e.target.select()} />
                        <Button variant="outline" onClick={() => copy(created.token)}><Copy />Copy</Button>
                    </CardContent>
                </Card>
            )}

            <h2 className="mt-2 text-base font-medium">Your access tokens</h2>
            <Card className="overflow-hidden py-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Last used</TableHead>
                            <TableHead>Expires</TableHead>
                            <TableHead className="w-12"><span className="sr-only">Revoke</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tokens.length === 0 && (
                            <TableRow><TableCell colSpan={4} className="py-10 text-center text-muted-foreground">No tokens yet.</TableCell></TableRow>
                        )}
                        {tokens.map((t) => (
                            <TableRow key={t.id}>
                                <TableCell>
                                    <span className="font-medium">{t.name}</span>{' '}
                                    {t.readOnly && <Badge variant="secondary">Read-only</Badge>}{' '}
                                    {t.expired && <Badge variant="destructive">Expired</Badge>}
                                    <div className="text-xs text-muted-foreground">Created {when(t.createdAt)}</div>
                                </TableCell>
                                <TableCell className="text-sm">{when(t.lastUsedAt)}{t.lastUsedIp && <div className="text-xs text-muted-foreground">from {t.lastUsedIp}</div>}</TableCell>
                                <TableCell className="text-sm">{t.expiresAt ? when(t.expiresAt) : 'Never'}</TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="icon" className="text-destructive" aria-label={`Revoke ${t.name}`}
                                        onClick={() => confirm(`Revoke "${t.name}"? Agents using it stop working at once.`, () => router.delete(adminUrl(`ai/tokens/${t.id}`), { preserveScroll: true }), true)}>
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
                            <DialogTitle>New AI access token</DialogTitle>
                            <DialogDescription>Name it after the agent or computer that uses it, so you know what to revoke.</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="token-name">Name</Label>
                            <Input id="token-name" required maxLength={100} placeholder="Claude on my laptop" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox id="token-read-only" checked={form.data.read_only} onCheckedChange={(checked) => form.setData('read_only', checked === true)} />
                            <Label htmlFor="token-read-only" className="font-normal">Read-only: the agent can look, but not change anything</Label>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="token-expires">Expires</Label>
                            <Select value={form.data.expires_in_days} onValueChange={(value) => form.setData('expires_in_days', value)}>
                                <SelectTrigger id="token-expires" className="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NEVER}>Never</SelectItem>
                                    <SelectItem value="30">In 30 days</SelectItem>
                                    <SelectItem value="90">In 90 days</SelectItem>
                                    <SelectItem value="365">In a year</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreating(false)}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}>Create token</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

/** A labelled block of text to copy: an address, a command, a config. */
function CodeBlock({ label, code, onCopy }: { label: string; code: string; onCopy: (text: string) => void }) {
    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-2">
                <Label>{label}</Label>
                <Button type="button" variant="ghost" size="sm" onClick={() => onCopy(code)}><Copy />Copy</Button>
            </div>
            <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2.5 font-mono text-xs leading-relaxed"><code>{code}</code></pre>
        </div>
    );
}
