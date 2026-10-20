/**
 * Whether a link address is safe to keep: http(s), mailto, tel, or a path
 * (what the server's sanitisers keep), never javascript: or data:.
 */
export function isSafeUrl(url: string): boolean {
    // Browsers ignore whitespace and control characters inside a scheme.
    const clean = url.replace(/[\u0000- \u007f]+/g, '');
    const scheme = /^([a-zA-Z][a-zA-Z0-9+.-]*):/.exec(clean);
    if (scheme) return ['http', 'https', 'mailto', 'tel'].includes(scheme[1]!.toLowerCase());

    return !clean.includes('\\');
}
