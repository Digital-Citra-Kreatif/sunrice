import * as React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
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
    const form = useForm<{ name: string; read_only: boolean; expires_in_days: string }>({ name: '', read_only: false, expires_in_days: NEVER });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, expires_in_days: data.expires_in_days === NEVER ? null : Number(data.expires_in_days) }));
        form.post(adminUrl('ai/tokens'), { preserveScroll: true, onSuccess: () => { setCreating(false); form.reset(); } });
    };
    const copy = async (text: string) => {
        await navigator.clipboard.writeText(text);
        toast.success('Copied.');
    };

    const token = created?.token ?? 'sra_…';
    const claudeCode = `claude mcp add --transport http panel ${endpoint} --header "Authorization: Bearer ${token}"`;
    const json = JSON.stringify({ mcpServers: { panel: { type: 'http', url: endpoint, headers: { Authorization: `Bearer ${token}` } } } }, null, 2);

    return (
        <div className="flex max-w-4xl flex-col gap-6">
            <Head title="AI access" />
            {confirmDialog}
            <PageHeader
                title="AI access"
                description="Let AI agents (Claude, ChatGPT, Cursor …) work in the panel as you, with your permissions."
                actions={<Button onClick={() => setCreating(true)}><Plus />New token</Button>}
            />

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

            <Card>
                <CardHeader>
                    <CardTitle>Connecting an agent</CardTitle>
                    <CardDescription>Give the agent the panel's MCP address and a token{created ? '' : ' (create one above)'}. Read-only tokens can look but not change anything.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm">
                    <div className="grid gap-1.5">
                        <Label>Claude Code</Label>
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs"><code>{claudeCode}</code></pre>
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Claude Desktop, Cursor and other MCP clients</Label>
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs"><code>{json}</code></pre>
                    </div>
                </CardContent>
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
