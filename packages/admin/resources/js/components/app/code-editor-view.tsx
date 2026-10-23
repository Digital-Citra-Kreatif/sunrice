import * as React from 'react';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { bracketMatching, HighlightStyle, indentOnInput, syntaxHighlighting } from '@codemirror/language';
import { Compartment, EditorState, type Extension } from '@codemirror/state';
import {
    drawSelection, EditorView, highlightActiveLine, highlightActiveLineGutter, keymap, lineNumbers, placeholder as placeholderText,
} from '@codemirror/view';
import { tags } from '@lezer/highlight';
import { codeEditorLines } from '@/lib/code-editor';
import { cn } from '@/lib/utils';
import { CODE_EDITOR_FRAME, CODE_EDITOR_INVALID, type CodeEditorProps } from './code-editor';
import { loadedLanguage, loadLanguage } from './code-editor-languages';

/* Colours from the panel's stylesheet (--code-*), so light and dark mode follow the theme. */
const highlight = HighlightStyle.define([
    { tag: [tags.keyword, tags.operatorKeyword, tags.modifier, tags.controlKeyword], color: 'var(--code-keyword)' },
    { tag: [tags.tagName, tags.typeName, tags.className], color: 'var(--code-tag)' },
    { tag: [tags.attributeName, tags.propertyName], color: 'var(--code-attribute)' },
    { tag: [tags.string, tags.special(tags.string), tags.attributeValue, tags.regexp], color: 'var(--code-string)' },
    { tag: [tags.number, tags.bool, tags.null, tags.atom, tags.unit, tags.color], color: 'var(--code-number)' },
    { tag: [tags.comment, tags.meta, tags.documentMeta, tags.processingInstruction], color: 'var(--code-comment)', fontStyle: 'italic' },
    { tag: [tags.function(tags.variableName), tags.function(tags.propertyName), tags.labelName], color: 'var(--code-function)' },
    { tag: [tags.angleBracket, tags.bracket, tags.punctuation, tags.separator], color: 'var(--code-punctuation)' },
    { tag: tags.invalid, color: 'var(--destructive)' },
]);

function theme(lines: { min: number; max: number }): Extension {
    // Lines of 1.6em plus the content's 8px above and below: it grows from min to max lines, then scrolls.
    return EditorView.theme({
        '&': { color: 'var(--foreground)', backgroundColor: 'transparent', fontSize: '13px' },
        '&.cm-focused': { outline: 'none' },
        '.cm-scroller': {
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace', lineHeight: '1.6',
            minHeight: `calc(${lines.min * 1.6}em + 16px)`, maxHeight: `calc(${lines.max * 1.6}em + 16px)`, overflow: 'auto',
        },
        '.cm-content': { padding: '8px 0', caretColor: 'var(--foreground)' },
        '.cm-line': { padding: '0 12px 0 8px' },
        '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--foreground)' },
        '&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
            backgroundColor: 'color-mix(in oklab, var(--primary) 22%, transparent)',
        },
        // The cursor's line only while editing, so a form of several editors doesn't mark each one's first line.
        '.cm-activeLine': { backgroundColor: 'transparent' },
        '&.cm-focused .cm-activeLine': { backgroundColor: 'color-mix(in oklab, var(--muted) 55%, transparent)' },
        '.cm-gutters': { backgroundColor: 'color-mix(in oklab, var(--muted) 45%, transparent)', color: 'var(--muted-foreground)', border: 'none', borderRight: '1px solid var(--border)' },
        '.cm-lineNumbers .cm-gutterElement': { padding: '0 8px 0 10px', minWidth: '2.5ch' },
        '.cm-activeLineGutter': { backgroundColor: 'transparent' },
        '&.cm-focused .cm-activeLineGutter': { color: 'var(--foreground)' },
        '&.cm-focused .cm-matchingBracket': { backgroundColor: 'color-mix(in oklab, var(--primary) 18%, transparent)', outline: '1px solid color-mix(in oklab, var(--primary) 45%, transparent)' },
        '&.cm-focused .cm-nonmatchingBracket': { backgroundColor: 'color-mix(in oklab, var(--destructive) 20%, transparent)' },
        '.cm-placeholder': { color: 'var(--muted-foreground)' },
    });
}

function accessibility({ id, invalid, readOnly, disabled, ...props }: CodeEditorProps): Extension {
    const attributes: Record<string, string> = { spellcheck: 'false', autocapitalize: 'off', autocorrect: 'off' };
    if (id) attributes.id = id;
    if (props['aria-labelledby']) attributes['aria-labelledby'] = props['aria-labelledby'];
    if (props['aria-describedby']) attributes['aria-describedby'] = props['aria-describedby'];
    if (invalid) attributes['aria-invalid'] = 'true';
    if (readOnly || disabled) attributes['aria-readonly'] = 'true';
    if (disabled) attributes['aria-disabled'] = 'true';

    return EditorView.contentAttributes.of(attributes);
}

function editable({ disabled, readOnly }: CodeEditorProps): Extension {
    return [EditorView.editable.of(!disabled), EditorState.readOnly.of(disabled === true || readOnly === true)];
}

/** CodeMirror itself, in its own chunk (see code-editor.tsx). */
export function CodeMirrorEditor(props: CodeEditorProps) {
    const { value, onChange, language = 'plain', invalid, disabled, minLines, maxLines, placeholder, className } = props;
    const host = React.useRef<HTMLDivElement>(null);
    const view = React.useRef<EditorView | null>(null);
    const change = React.useRef(onChange);
    change.current = onChange;
    const compartments = React.useMemo(() => ({ language: new Compartment(), theme: new Compartment(), aria: new Compartment(), editable: new Compartment(), placeholder: new Compartment() }), []);
    const lines = codeEditorLines(minLines, maxLines);

    React.useEffect(() => {
        if (!host.current) return undefined;
        const editor = new EditorView({
            parent: host.current,
            state: EditorState.create({
                doc: value,
                extensions: [
                    lineNumbers(), highlightActiveLineGutter(), highlightActiveLine(), drawSelection(), history(), indentOnInput(), bracketMatching(),
                    syntaxHighlighting(highlight),
                    // Tab indents. CodeMirror's escape hatch keeps it from trapping the keyboard: Esc, then Tab moves on (Ctrl+M toggles it).
                    keymap.of([...defaultKeymap, ...historyKeymap, indentWithTab]),
                    EditorState.tabSize.of(4),
                    EditorView.lineWrapping,
                    compartments.language.of(loadedLanguage(language) ?? []),
                    compartments.theme.of(theme(lines)),
                    compartments.aria.of(accessibility(props)),
                    compartments.editable.of(editable(props)),
                    compartments.placeholder.of(placeholder ? placeholderText(placeholder) : []),
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged) change.current(update.state.doc.toString());
                    }),
                ],
            }),
        });
        view.current = editor;

        return () => {
            editor.destroy();
            view.current = null;
        };
        // Created once; the effects below keep it in step with the props.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // A value set from outside (a reset, loaded changes): replace the text, keeping what can be of the selection.
    React.useEffect(() => {
        const editor = view.current;
        if (editor && editor.state.doc.toString() !== value) {
            const at = Math.min(editor.state.selection.main.head, value.length);
            editor.dispatch({ changes: { from: 0, to: editor.state.doc.length, insert: value }, selection: { anchor: at } });
        }
    }, [value]);

    // The language's grammar (its own chunk), once loaded: usually before the editor itself.
    React.useEffect(() => {
        let current = true;
        void loadLanguage(language).then((support) => {
            if (current) view.current?.dispatch({ effects: compartments.language.reconfigure(support) });
        });

        return () => {
            current = false;
        };
    }, [language, compartments]);
    React.useEffect(() => {
        view.current?.dispatch({ effects: compartments.theme.reconfigure(theme(lines)) });
    }, [lines.min, lines.max, compartments]); // eslint-disable-line react-hooks/exhaustive-deps
    React.useEffect(() => {
        view.current?.dispatch({
            effects: [
                compartments.aria.reconfigure(accessibility(props)),
                compartments.editable.reconfigure(editable(props)),
                compartments.placeholder.reconfigure(placeholder ? placeholderText(placeholder) : []),
            ],
        });
    }, [props.id, props['aria-labelledby'], props['aria-describedby'], invalid, disabled, props.readOnly, placeholder, compartments]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div ref={host} data-slot="code-editor" data-invalid={invalid || undefined}
            className={cn(CODE_EDITOR_FRAME, invalid && CODE_EDITOR_INVALID, disabled && 'cursor-not-allowed opacity-50', className)} />
    );
}
