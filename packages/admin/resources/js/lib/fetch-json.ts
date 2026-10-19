/**
 * fetch() for the panel's JSON endpoints: same-origin cookies, Laravel's
 * CSRF token (from the XSRF-TOKEN cookie) and JSON errors.
 */
export class RequestError extends Error {
    constructor(message: string, public status: number, public errors: Record<string, string[]> = {}) {
        super(message);
    }
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

export async function fetchJson<T>(url: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    const token = xsrfToken();
    if (token && init.method && init.method !== 'GET') headers.set('X-XSRF-TOKEN', token);

    const response = await fetch(url, { credentials: 'same-origin', ...init, headers });
    const body = await response.json().catch(() => null) as { message?: string; errors?: Record<string, string[]> } | null;
    if (!response.ok) {
        const errors = body?.errors ?? {};
        throw new RequestError(Object.values(errors)[0]?.[0] ?? body?.message ?? `The request failed (${response.status}).`, response.status, errors);
    }
    return body as T;
}

/** A URL with query parameters (arrays as name[]=…). */
export function withQuery(url: string, params: Record<string, string | number | null | undefined | (string | number)[]>): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (Array.isArray(value)) value.forEach((item) => search.append(`${key}[]`, String(item)));
        else if (value !== null && value !== undefined && value !== '') search.append(key, String(value));
    }
    const query = search.toString();
    return query ? `${url}${url.includes('?') ? '&' : '?'}${query}` : url;
}
