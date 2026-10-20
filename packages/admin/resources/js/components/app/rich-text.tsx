import * as React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export interface RichTextEditorProps {
    id: string;
    /** The HTML. */
    value: string;
    /** The new HTML, or null when the text is empty. */
    onChange: (html: string | null) => void;
    invalid?: boolean;
    /** The label's id, for the editing area's name. */
    labelledBy?: string;
    /** Offer images from the media library (needs sunrice.media.view). */
    images?: boolean;
    disabled?: boolean;
}

/* TipTap and ProseMirror are large: their chunk loads when an editor first shows, never with the panel's main script. */
const Editor = React.lazy(() => import('./rich-text-editor').then((module) => ({ default: module.RichTextEditor })));

/**
 * The panel's rich text editor: headings, lists, quotes, code, links and
 * media library images, the formatting the server keeps when it sanitises
 * the HTML (the RichText resource field, the CMS's rich text fields).
 */
export function RichTextEditor(props: RichTextEditorProps) {
    return (
        <React.Suspense fallback={<Skeleton className="h-48 w-full" />}>
            <Editor {...props} />
        </React.Suspense>
    );
}
