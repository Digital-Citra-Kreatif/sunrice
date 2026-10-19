/** Page data of the Media page (see MediaController). */

export interface MediaItem {
    id: number;
    title: string | null;
    filename: string;
    alt: string | null;
    caption: string | null;
    mimeType: string;
    size: number;
    width: number | null;
    height: number | null;
    folderId: number | null;
    url: string;
    thumbnail: string | null;
    createdAt: string | null;
    trashed: boolean;
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
