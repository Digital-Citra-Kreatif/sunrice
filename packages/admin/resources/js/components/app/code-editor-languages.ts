import type { Extension } from '@codemirror/state';
import type { CodeLanguage } from '@/lib/code-editor';

/**
 * HTML's options: no closing tags typed for you. CodeMirror's autoCloseTags adds `</div>` on `>` (and on `</`), so
 * someone typing the whole `<div>Hi</div>`, or pasting it a character at a time, would save `<div>Hi</div></div>`.
 */
export const HTML_OPTIONS = { autoCloseTags: false } as const;

/* Each language's grammar is a chunk of its own, loaded the first time an editor needs it (HTML brings CSS and JavaScript). */
const loaders: Record<Exclude<CodeLanguage, 'plain'>, () => Promise<Extension>> = {
    html: () => import('@codemirror/lang-html').then((module) => module.html(HTML_OPTIONS)),
    css: () => import('@codemirror/lang-css').then((module) => module.css()),
    javascript: () => import('@codemirror/lang-javascript').then((module) => module.javascript()),
    json: () => import('@codemirror/lang-json').then((module) => module.json()),
};

const pending = new Map<CodeLanguage, Promise<Extension>>();
const loaded = new Map<CodeLanguage, Extension>([['plain', []]]);

/** The language's support, once loaded (plain text at once); null until then. */
export function loadedLanguage(language: CodeLanguage): Extension | null {
    return loaded.get(language) ?? null;
}

/** Loads the language's support (once); a grammar that fails to load leaves the code plain. */
export function loadLanguage(language: CodeLanguage): Promise<Extension> {
    const ready = loaded.get(language);
    if (ready !== undefined) return Promise.resolve(ready);
    let promise = pending.get(language);
    if (!promise) {
        promise = loaders[language as Exclude<CodeLanguage, 'plain'>]()
            .then((extension) => {
                loaded.set(language, extension);
                return extension;
            })
            .catch(() => {
                pending.delete(language);
                return [];
            });
        pending.set(language, promise);
    }

    return promise;
}
