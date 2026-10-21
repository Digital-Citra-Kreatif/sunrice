import * as React from 'react';
import { router } from '@inertiajs/react';
import { History, Lock, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { adminUrl } from '@/lib/admin-url';
import { fetchJson } from '@/lib/fetch-json';

export interface KeptEdit { id: number; by: string | null; taken_by: string | null; at: string | null }

type LockState =
    | { status: 'starting' }
    | { status: 'holding' }
    | { status: 'locked'; by: string; since: number; confirming: boolean }
    | { status: 'viewing'; by: string }
    | { status: 'taken'; by: string };

export interface EditLockOptions {
    /** A type registered with $panel->lockable(): "cms.entry". */
    type: string;
    /** The item; null for one not created yet (no lock). */
    id: string | number | null | undefined;
    /** The language edited, when the type locks per language. */
    locale?: string;
    /** Off for new items and for people who may only view. */
    enabled: boolean;
    /** The unsaved changes to keep if someone takes over; null when there are none. */
    getUnsaved: () => Record<string, unknown> | null;
    /** Where to go after being taken over, or when going back. */
    leaveTo: string;
    /** What the item is called in messages: "entry". */
    noun?: string;
}

export interface EditLock {
    /** Someone else holds the lock (or took it): no saving here. */
    readOnly: boolean;
    /** Who's editing while this page only views. */
    viewingBy: string | null;
    /** True once taken over: the unsaved-changes prompt mustn't block leaving. */
    takenOver: boolean;
    kept: KeptEdit[];
    loadKept: (id: number) => Promise<Record<string, unknown>>;
    discardKept: (id: number) => Promise<void>;
    /** While viewing: asks to take over (the confirm dialog). */
    requestTakeOver: () => void;
    /** The dialogs: render them in the page. */
    dialogs: React.ReactNode;
}

const RENEW_EVERY = 15_000;

/** One id per browser tab, kept across reloads, so a tab recognises its own lock. */
function editorId(): string {
    try {
        let id = window.sessionStorage.getItem('sunrice-editor-id');
        if (!id) {
            id = Math.random().toString(36).slice(2) + Date.now().toString(36);
            window.sessionStorage.setItem('sunrice-editor-id', id);
        }
        return id;
    } catch {
        return 'tab-' + Math.random().toString(36).slice(2);
    }
}

/**
 * The edit lock of an editor page (Sunrice\Admin\Locks\EditLocks): holds it
 * while the page is open, says when someone else is editing (go back, view
 * only or take over), and hands over cleanly when taken over, keeping the
 * unsaved changes. Shared with plugins as `useEditLock` in @sunrice/admin-sdk/ui.
 */
export function useEditLock({ type, id, locale = '', enabled, getUnsaved, leaveTo, noun = 'item' }: EditLockOptions): EditLock {
    const [state, setState] = React.useState<LockState>({ status: 'starting' });
    const [kept, setKept] = React.useState<KeptEdit[]>([]);
    const editor = React.useMemo(editorId, []);
    const unsavedRef = React.useRef(getUnsaved);
    unsavedRef.current = getUnsaved;
    const base = id !== null && id !== undefined && id !== '' ? adminUrl(`locks/${type}/${id}`) : '';

    const post = React.useCallback(<T,>(path: string, body: Record<string, unknown> = {}, keepalive = false) => fetchJson<T>(base + path, {
        method: 'POST',
        keepalive,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ editor, locale, ...body }),
    }), [base, editor, locale]);

    const stateRef = React.useRef(state);
    stateRef.current = state;

    type Renewed = { status: 'ok' | 'locked' | 'taken'; by?: string; since?: number; kept?: KeptEdit[] };
    const renew = React.useCallback(async () => {
        const result = await post<Renewed>('').catch(() => null);
        if (!result) return;
        setKept(result.kept ?? []);
        if (result.status === 'ok') {
            setState({ status: 'holding' });
        } else if (result.status === 'locked') {
            // Viewing stays viewing; otherwise ask what to do.
            if (stateRef.current.status !== 'viewing') setState({ status: 'locked', by: result.by ?? 'Someone', since: result.since ?? 0, confirming: false });
        } else if (result.status === 'taken' && stateRef.current.status !== 'taken') {
            const unsaved = unsavedRef.current();
            if (unsaved) await post('/keep', { content: unsaved, taken_by: result.by }).catch(() => null);
            setState({ status: 'taken', by: result.by ?? 'Someone' });
        }
    }, [post]);

    // Take the lock when the page (or the language) opens; release it when it closes.
    React.useEffect(() => {
        if (!enabled || !base) return;
        setState({ status: 'starting' });
        void renew();
        const release = () => { void post('/release', {}, true).catch(() => null); };
        window.addEventListener('pagehide', release);
        return () => {
            window.removeEventListener('pagehide', release);
            if (stateRef.current.status === 'holding') release();
        };
    }, [enabled, base, locale, renew, post]);

    // Renew while holding (learning about a takeover), and keep trying when the first request failed.
    React.useEffect(() => {
        if (!enabled || (state.status !== 'holding' && state.status !== 'starting')) return;
        const timer = window.setInterval(() => { void renew(); }, RENEW_EVERY);
        return () => window.clearInterval(timer);
    }, [enabled, state.status, renew]);

    const takeOver = async () => {
        try {
            await post('/take');
            // Reload to edit the latest saved version.
            window.location.reload();
        } catch {
            toast.error('Couldn\'t take over. Try again.');
        }
    };

    const loadKept = async (keptId: number) => (await fetchJson<{ content: Record<string, unknown> }>(adminUrl(`kept-edits/${keptId}`))).content;
    const discardKept = async (keptId: number) => {
        await fetchJson(adminUrl(`kept-edits/${keptId}`), { method: 'DELETE' }).catch(() => null);
        setKept((k) => k.filter((e) => e.id !== keptId));
    };

    const since = state.status === 'locked' && state.since > 0 ? new Date(state.since * 1000).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
    const language = locale ? ` (${locale.toUpperCase()})` : '';

    const dialogs = (
        <>
            <Dialog open={state.status === 'locked'}>
                <DialogContent showCloseButton={false}>
                    {state.status === 'locked' && !state.confirming && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2"><Lock className="size-4" /> {state.by} is editing this {noun}{language}</DialogTitle>
                                <DialogDescription>
                                    {since ? `They've had it open since ${since}. ` : ''}If you take over, their unsaved changes are kept for them to load again, and they're sent back.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => router.visit(leaveTo)}>Go back</Button>
                                <Button variant="outline" onClick={() => setState({ status: 'viewing', by: state.by })}>View only</Button>
                                <Button onClick={() => setState({ ...state, confirming: true })}>Take over</Button>
                            </DialogFooter>
                        </>
                    )}
                    {state.status === 'locked' && state.confirming && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Take over from {state.by}?</DialogTitle>
                                <DialogDescription>
                                    {state.by} is told you took over and can't save here any more. What they haven't saved is kept separately; you can load it if you need it.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setState({ ...state, confirming: false })}>Cancel</Button>
                                <Button variant="destructive" onClick={takeOver}>Take over</Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
            <Dialog open={state.status === 'taken'}>
                <DialogContent showCloseButton={false}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2"><UserRound className="size-4" /> {state.status === 'taken' ? state.by : 'Someone'} took over</DialogTitle>
                        <DialogDescription>
                            They're editing this {noun}{language} now. Your unsaved changes were kept: they show on this page, ready to load, when it's opened again.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button onClick={() => router.visit(leaveTo)}>Go back</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );

    return {
        readOnly: state.status === 'locked' || state.status === 'viewing' || state.status === 'taken',
        viewingBy: state.status === 'viewing' ? state.by : null,
        takenOver: state.status === 'taken',
        kept,
        loadKept,
        discardKept,
        requestTakeOver: () => {
            const current = stateRef.current;
            if (current.status === 'viewing') setState({ status: 'locked', by: current.by, since: 0, confirming: true });
        },
        dialogs,
    };
}

/** "Ana's unsaved changes were kept" notices, with Load and Discard. */
export function KeptEditsNotice({ kept, onLoad, onDiscard, disabled }: {
    kept: KeptEdit[];
    onLoad: (id: number) => void;
    onDiscard: (id: number) => void;
    disabled?: boolean;
}) {
    if (kept.length === 0) return null;
    return (
        <div className="flex flex-col gap-2">
            {kept.map((k) => (
                <div key={k.id} className="flex flex-wrap items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm">
                    <History className="size-4 text-muted-foreground" />
                    <span className="flex-1">
                        Unsaved changes of <strong>{k.by ?? 'another editor'}</strong>
                        {k.taken_by ? `, kept when ${k.taken_by} took over` : ''}
                        {k.at ? ` (${new Date(k.at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })})` : ''}.
                    </span>
                    <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => onLoad(k.id)}>Load into the form</Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => onDiscard(k.id)}>Discard</Button>
                </div>
            ))}
        </div>
    );
}

/** The banner while viewing what someone else is editing, with Take over (useEditLock's requestTakeOver). */
export function ViewingNotice({ by, onTakeOver }: { by: string | null; onTakeOver?: () => void }) {
    if (!by) return null;
    return (
        <div role="status" className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground">
            <Lock className="size-4" /> <span className="flex-1">{by} is editing this. You're viewing it; saving is off.</span>
            {onTakeOver && <Button type="button" size="sm" variant="outline" onClick={onTakeOver}>Take over</Button>}
        </div>
    );
}
