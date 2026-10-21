/** A site a profile address is on, for ListInput's ->networks(). */
export interface Network {
    name: string;
    /** A short mark for its chip ("in", "X"). */
    mark: string;
    /** Its brand colour, for the chip. */
    color: string;
}

const NETWORKS: { hosts: string[]; network: Network }[] = [
    { hosts: ['instagram.com'], network: { name: 'Instagram', mark: 'IG', color: '#d62976' } },
    { hosts: ['linkedin.com'], network: { name: 'LinkedIn', mark: 'in', color: '#0a66c2' } },
    { hosts: ['facebook.com', 'fb.com'], network: { name: 'Facebook', mark: 'f', color: '#1877f2' } },
    { hosts: ['x.com', 'twitter.com'], network: { name: 'X', mark: 'X', color: '#111111' } },
    { hosts: ['youtube.com', 'youtu.be'], network: { name: 'YouTube', mark: '▶', color: '#ff0000' } },
    { hosts: ['tiktok.com'], network: { name: 'TikTok', mark: 'TT', color: '#111111' } },
    { hosts: ['wikipedia.org', 'wikidata.org'], network: { name: 'Wikipedia', mark: 'W', color: '#555555' } },
    { hosts: ['threads.net', 'threads.com'], network: { name: 'Threads', mark: '@', color: '#111111' } },
    { hosts: ['pinterest.com'], network: { name: 'Pinterest', mark: 'P', color: '#e60023' } },
    { hosts: ['github.com'], network: { name: 'GitHub', mark: 'GH', color: '#24292f' } },
    { hosts: ['wa.me', 'whatsapp.com'], network: { name: 'WhatsApp', mark: 'WA', color: '#25d366' } },
    { hosts: ['google.com', 'g.page', 'goo.gl'], network: { name: 'Google', mark: 'G', color: '#4285f4' } },
];

/** The site an address is on, by its host (subdomains too: id.linkedin.com), or null for any other or a bad address. */
export function networkOf(address: string): Network | null {
    let host: string;
    try {
        host = new URL(address.trim()).hostname.toLowerCase();
    } catch {
        return null;
    }
    const found = NETWORKS.find(({ hosts }) => hosts.some((known) => host === known || host.endsWith(`.${known}`)));

    return found?.network ?? null;
}

/** Whether a list row looks like what the field takes (the server checks it properly), to mark the rows a refused save was about. */
export function looksValid(row: string, accepts: 'url' | 'email' | null | undefined, httpsOnly = false): boolean {
    const value = row.trim();
    if (value === '' || !accepts) return true;
    if (accepts === 'email') return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    if (!(httpsOnly ? /^https:\/\//i : /^https?:\/\//i).test(value)) return false;
    try {
        return new URL(value).hostname.includes('.');
    } catch {
        return false;
    }
}

/** A list field's text as rows, and back: one value per line (email addresses split at commas and spaces too). */
export function parseList(text: string, emails = false): string[] {
    if (text === '') return [];

    return emails ? text.split(/[\s,;]+/).filter((row) => row !== '') : text.split(/\r?\n/);
}

export function formatList(rows: string[]): string {
    return rows.join('\n');
}
