/** What copyText() needs from the browser (passed in by tests). */
export interface ClipboardEnv {
    isSecureContext: boolean;
    clipboard?: { writeText(text: string): Promise<void> } | undefined;
    document: Pick<Document, 'createElement' | 'execCommand' | 'activeElement' | 'body'>;
}

const browser = (): ClipboardEnv => ({ isSecureContext: window.isSecureContext, clipboard: navigator.clipboard, document });

/**
 * Copy text to the clipboard. The Clipboard API exists only on secure
 * pages (https or localhost); on a plain-http address, such as a
 * development host like http://shop.test, it falls back to a hidden
 * textarea and execCommand('copy'). Rejects when neither works.
 */
export async function copyText(text: string, env: ClipboardEnv = browser()): Promise<void> {
    if (env.isSecureContext && env.clipboard?.writeText) {
        await env.clipboard.writeText(text);
        return;
    }

    const doc = env.document;
    // Inside an open dialog, so its focus trap doesn't pull the focus back.
    const host = doc.activeElement?.closest('[role="dialog"]') ?? doc.body;
    const area = doc.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.top = '0';
    area.style.left = '0';
    area.style.opacity = '0';
    host.appendChild(area);
    const previous = doc.activeElement as HTMLElement | null;
    area.focus();
    area.select();
    let copied = false;
    try {
        copied = doc.execCommand('copy');
    } finally {
        area.remove();
        previous?.focus?.();
    }
    if (!copied) {
        throw new Error('Copy failed');
    }
}
