import * as React from 'react';
import { router } from '@inertiajs/react';
import { adminUrl } from '@/lib/admin-url';
import { uploadProblem } from './format';

export interface Upload {
    key: number;
    name: string;
    progress: number;
    status: 'waiting' | 'uploading' | 'done' | 'failed';
    error: string | null;
}

let nextKey = 1;

/**
 * Uploads files one after another into a folder (one request each, so
 * each file gets its own errors), with progress.
 */
export function useUploads(folderId: number | null, limits: { maxKb: number; accept: string }) {
    const [uploads, setUploads] = React.useState<Upload[]>([]);
    const queue = React.useRef<{ key: number; file: File }[]>([]);
    const running = React.useRef(false);

    const update = (key: number, changes: Partial<Upload>) =>
        setUploads((list) => list.map((upload) => (upload.key === key ? { ...upload, ...changes } : upload)));

    const send = (key: number, file: File) => new Promise<void>((resolve) => {
        let settled = false;
        const done = (changes: Partial<Upload>) => {
            if (!settled) {
                settled = true;
                update(key, changes);
                resolve();
            }
        };
        router.post(adminUrl('media'), { file, folder_id: folderId }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => update(key, { status: 'uploading' }),
            onProgress: (event) => update(key, { progress: Math.round(event?.percentage ?? 0) }),
            onSuccess: () => done({ status: 'done', progress: 100 }),
            onError: (errors) => done({ status: 'failed', error: errors.file ?? Object.values(errors)[0] ?? 'The upload failed.' }),
            onFinish: () => done({ status: 'failed', error: 'The upload was interrupted.' }),
        });
    });

    const run = async () => {
        if (running.current) return;
        running.current = true;
        while (queue.current.length > 0) {
            const next = queue.current.shift()!;
            await send(next.key, next.file);
        }
        running.current = false;
    };

    const add = (files: FileList | File[]) => {
        const added: Upload[] = [];
        for (const file of Array.from(files)) {
            const key = nextKey++;
            const problem = uploadProblem(file, limits.maxKb, limits.accept);
            added.push({ key, name: file.name, progress: 0, status: problem ? 'failed' : 'waiting', error: problem });
            if (!problem) queue.current.push({ key, file });
        }
        setUploads((list) => [...list.filter((upload) => upload.status !== 'done'), ...added]);
        void run();
    };

    const clear = () => setUploads((list) => list.filter((upload) => upload.status === 'waiting' || upload.status === 'uploading'));

    return { uploads, add, clear };
}
