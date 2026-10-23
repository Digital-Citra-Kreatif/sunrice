/** Page data of the Media page (see MediaController). */

export interface MediaItem {
    id: number;
    title: string | null;
    filename: string;
    alt: string | null;
    caption: string | null;
    /** Alt text and caption in the content's other languages, by language code. */
    translations?: Record<string, { alt?: string; caption?: string }>;
    mimeType: string;
    size: number;
    width: number | null;
    height: number | null;
    folderId: number | null;
    /** Its public URL; null in the trash (its files are offline, on the private trash disk). */
    url: string | null;
    thumbnail: string | null;
    createdAt: string | null;
    trashed: boolean;
    /** How many places use it (MediaUsages); null where not counted. */
    usages?: number | null;
    /** In the trash: what permanently deleting it does (broken links only when it's used). */
    deleteWarning?: string | null;
}

/** One place a file is used. */
export interface MediaUsageInfo {
    type: string;
    label: string;
    url: string | null;
}

export interface FolderInfo {
    id: number;
    name: string;
}

export interface TreeFolder extends FolderInfo {
    depth: number;
}

export interface MediaQuery {
    folder: number | null;
    search: string;
    type: 'image' | 'video' | 'audio' | 'document' | null;
    trash: boolean;
}
