/** Helpers for the media library, kept free of React so node tests can run them. */

/** 1536 → "1.5 KB". */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${value >= 10 ? Math.round(value) : Math.round(value * 10) / 10} ${units[unit]}`;
}

/** Says a message in the user's language: pass `t` from '@/lib/i18n' (this file can't import it, for the node tests). */
export type Say = (text: string, replace?: Record<string, string>) => string;

/** The English, with Laravel-style :placeholders filled in. */
const english: Say = (text, replace = {}) => Object.entries(replace).reduce((out, [name, value]) => out.replaceAll(`:${name}`, value), text);

/**
 * Why a file can't be uploaded, checked before sending it (the server
 * checks again): too large, or an extension that isn't accepted.
 */
export function uploadProblem(file: { name: string; size: number }, maxKb: number, accept: string, say: Say = english): string | null {
    const extension = file.name.includes('.') ? `.${file.name.split('.').pop()!.toLowerCase()}` : '';
    const accepted = accept.split(',').map((ext) => ext.trim().toLowerCase()).filter(Boolean);
    if (accepted.length > 0 && !accepted.includes(extension)) {
        return extension === '' ? say('Files without an extension can\'t be uploaded.') : say(':extension files can\'t be uploaded.', { extension });
    }
    if (file.size > maxKb * 1024) {
        return say('Larger than the :size limit.', { size: formatBytes(maxKb * 1024) });
    }
    return null;
}

/** "image" for image/png and so on: the kind of file, for its icon. */
export function mediaKind(mimeType: string): 'image' | 'video' | 'audio' | 'document' {
    const kind = mimeType.split('/')[0];
    return kind === 'image' || kind === 'video' || kind === 'audio' ? kind : 'document';
}
