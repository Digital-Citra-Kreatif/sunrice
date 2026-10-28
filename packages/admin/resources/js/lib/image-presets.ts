/**
 * The rich text editor's image presets, stored as classes on the <img>
 * (no inline styles), so a site styles them in its own CSS:
 *
 *     <img class="sunrice-img sunrice-img-w-50 sunrice-img-left" …>
 *
 * Plus the image styles a developer configures (sunrice-admin.rich_text.image_classes),
 * any number at once, after the presets: `class="sunrice-img sunrice-img-w-50 rounded shadow"`.
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
    /** The image style classes, in the order they're written. */
    styles: string[];
}

/** A configured image style, as the editor gets it (the `imageStyles` prop). */
export interface ImageStyle {
    class: string;
    label: string;
}

/** A style's class: a CSS class name that isn't one of the presets'. */
export function isStyleClass(value: unknown): value is string {
    return typeof value === 'string' && /^[A-Za-z_][A-Za-z0-9_-]*$/.test(value) && !value.startsWith('sunrice-img');
}

/** The image styles in a prop or field definition: well-formed ones only, each class once. */
export function imageStylesOf(value: unknown): ImageStyle[] {
    if (!Array.isArray(value)) return [];
    const seen = new Set<string>();
    return value.flatMap((item): ImageStyle[] => {
        if (item === null || typeof item !== 'object') return [];
        const { class: name, label } = item as Record<string, unknown>;
        if (!isStyleClass(name) || seen.has(name)) return [];
        seen.add(name);
        return [{ class: name, label: typeof label === 'string' && label.trim() !== '' ? label : name }];
    });
}

/** The styles with `style` turned on or off, in the configured order (styles not configured stay, after them). */
export function toggleStyle(styles: readonly string[], style: string, order: readonly string[] = []): string[] {
    const next = styles.includes(style) ? styles.filter((name) => name !== style) : [...styles, style];
    const rank = (name: string) => { const index = order.indexOf(name); return index === -1 ? order.length : index; };
    return next.map((name, index) => ({ name, index })).sort((a, b) => rank(a.name) - rank(b.name) || a.index - b.index).map(({ name }) => name);
}

function isWidth(value: unknown): value is ImageWidth {
    return typeof value === 'string' && (IMAGE_WIDTHS as readonly string[]).includes(value);
}

function isAlignment(value: unknown): value is ImageAlignment {
    return typeof value === 'string' && (IMAGE_ALIGNMENTS as readonly string[]).includes(value);
}

/** The class attribute for the presets and styles, or null when the image has none (it shows at its natural size). */
export function imageClass(presets: { width?: unknown; align?: unknown; styles?: unknown }): string | null {
    const classes: string[] = [];
    if (isWidth(presets.width)) classes.push(`sunrice-img-w-${presets.width}`);
    if (isAlignment(presets.align)) classes.push(`sunrice-img-${presets.align}`);
    const styles = Array.isArray(presets.styles) ? [...new Set(presets.styles.filter(isStyleClass))] : [];
    const all = [...(classes.length === 0 ? [] : ['sunrice-img', ...classes]), ...styles];

    return all.length === 0 ? null : all.join(' ');
}

/** The presets and styles in a class attribute; unknown `sunrice-img-*` classes are ignored. */
export function parseImageClass(className: string | null | undefined): ImagePresets {
    const presets: ImagePresets = { width: null, align: null, styles: [] };
    for (const name of (className ?? '').split(/\s+/)) {
        const width = /^sunrice-img-w-(\d+)$/.exec(name)?.[1];
        if (isWidth(width)) presets.width = width;
        const align = /^sunrice-img-([a-z]+)$/.exec(name)?.[1];
        if (isAlignment(align)) presets.align = align;
        if (isStyleClass(name) && !presets.styles.includes(name)) presets.styles.push(name);
    }

    return presets;
}
