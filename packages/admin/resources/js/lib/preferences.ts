import { adminUrl } from '@/lib/admin-url';
import { fetchJson } from '@/lib/fetch-json';

/**
 * Keeps one of the signed-in user's preferences on the server
 * (`PUT admin/preferences/{key}`; null forgets it). A failure is ignored:
 * the choice still holds on this page, it just isn't remembered.
 */
export async function savePreference(key: string, value: unknown): Promise<void> {
    await fetchJson(adminUrl(`preferences/${encodeURIComponent(key)}`), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ value }),
    }).catch(() => undefined);
}
