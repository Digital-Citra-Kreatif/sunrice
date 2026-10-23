/**
 * The rich text editor's image presets, stored as classes on the <img>
 * (no inline styles), so a site styles them in its own CSS:
 *
 *     <img class="sunrice-img sunrice-img-w-50 sunrice-img-left" …>
 *
 * The server keeps exactly these classes (RichTextImageClasses in PHP).
 */
export const IMAGE_WIDTHS = ['25', '50', '75', '100'] as const;
export const IMAGE_ALIGNMENTS = ['left', 'center', 'right', 'full'] as const;

export type ImageWidth = (typeof IMAGE_WIDTHS)[number];
export type ImageAlignment = (typeof IMAGE_ALIGNMENTS)[number];

export interface ImagePresets {
    width: ImageWidth | null;
    align: ImageAlignment | null;
}

function isWidth(value: unknown): value is ImageWidth {
    return typeof value === 'string' && (IMAGE_WIDTHS as readonly string[]).includes(value);
}

function isAlignment(value: unknown): value is ImageAlignment {
    return typeof value === 'string' && (IMAGE_ALIGNMENTS as readonly string[]).includes(value);
}

/** The class attribute for the presets, or null when the image has none (it shows at its natural size). */
export function imageClass(presets: { width?: unknown; align?: unknown }): string | null {
    const classes: string[] = [];
    if (isWidth(presets.width)) classes.push(`sunrice-img-w-${presets.width}`);
    if (isAlignment(presets.align)) classes.push(`sunrice-img-${presets.align}`);

    return classes.length === 0 ? null : ['sunrice-img', ...classes].join(' ');
}

/** The presets in a class attribute; other classes are ignored. */
export function parseImageClass(className: string | null | undefined): ImagePresets {
    const presets: ImagePresets = { width: null, align: null };
    for (const name of (className ?? '').split(/\s+/)) {
        const width = /^sunrice-img-w-(\d+)$/.exec(name)?.[1];
        if (isWidth(width)) presets.width = width;
        const align = /^sunrice-img-([a-z]+)$/.exec(name)?.[1];
        if (isAlignment(align)) presets.align = align;
    }

    return presets;
}
