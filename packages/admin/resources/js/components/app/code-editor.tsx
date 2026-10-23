import * as React from 'react';
import { codeEditorLines, type CodeLanguage } from '@/lib/code-editor';
import { loadLanguage } from './code-editor-languages';
import { cn } from '@/lib/utils';

export type { CodeLanguage };

export interface CodeEditorProps {
    /** The editing area's id (a label's htmlFor). */
    id?: string;
    value: string;
    onChange: (value: string) => void;
    /** What to highlight: html, css, javascript, json or plain (the default). */
    language?: CodeLanguage;
    invalid?: boolean;
    disabled?: boolean;
    /** Shown and selectable, not editable. */
    readOnly?: boolean;
    /** Lines shown when empty (4); it grows to `maxLines` (20), then scrolls. */
    minLines?: number;
    maxLines?: number;
    placeholder?: string;
    'aria-labelledby'?: string;
    'aria-describedby'?: string;
    className?: string;
}

/* CodeMirror is large: its chunk loads when an editor first shows, never with the panel's main script. */
const Editor = React.lazy(() => import('./code-editor-view').then((module) => ({ default: module.CodeMirrorEditor })));

/** The box around the editor and its fallback: an input's border, focus ring and invalid state. */
export const CODE_EDITOR_FRAME = 'w-full min-w-0 overflow-hidden rounded-md border border-input/80 bg-card font-mono text-[13px] shadow-xs transition-[color,border-color,box-shadow] duration-150 ease-out-quart hover:border-ring/45 focus-within:border-ring/70 focus-within:ring-4 focus-within:ring-ring-glow dark:bg-input/20';
export const CODE_EDITOR_INVALID = 'border-destructive ring-destructive/20 hover:border-destructive focus-within:border-destructive focus-within:ring-destructive/20 dark:ring-destructive/40';

/** A plain textarea while CodeMirror loads (and in server rendering): the same value, editable. */
function Fallback({ id, value, onChange, invalid, disabled, readOnly, minLines, maxLines, placeholder, className, ...aria }: CodeEditorProps) {
    const lines = codeEditorLines(minLines, maxLines);

    return (
        <textarea
            id={id} data-slot="code-editor" value={value} disabled={disabled} readOnly={readOnly} placeholder={placeholder}
            rows={Math.min(lines.max, Math.max(lines.min, value.split('\n').length))} spellCheck={false} autoCapitalize="off" autoCorrect="off"
            aria-invalid={invalid || undefined} aria-labelledby={aria['aria-labelledby']} aria-describedby={aria['aria-describedby']}
            onChange={(event) => onChange(event.target.value)}
            className={cn(CODE_EDITOR_FRAME, 'block resize-none px-3 py-2 leading-[1.6] outline-none disabled:cursor-not-allowed disabled:opacity-50', invalid && CODE_EDITOR_INVALID, className)}
        />
    );
}

/**
 * The panel's code editor (CodeMirror 6): line numbers, highlighting,
 * bracket matching and indentation. Tab indents; Esc then Tab (or
 * Ctrl+M, then Tab) moves on to the next control, so the keyboard never
 * gets stuck in it.
 */
export function CodeEditor(props: CodeEditorProps) {
    // The grammar loads beside the editor's own chunk, so the code shows highlighted at once.
    React.useEffect(() => {
        void loadLanguage(props.language ?? 'plain');
    }, [props.language]);

    return (
        <React.Suspense fallback={<Fallback {...props} />}>
            <Editor {...props} />
        </React.Suspense>
    );
}
