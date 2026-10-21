import * as React from 'react';
import { Check, ChevronRight, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { copyText } from '@/lib/clipboard';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * What a developer needs to know, folded away under a field's help
 * (Field::developerHelp()): a "For developers" disclosure with a sentence
 * and code shown as code, with a button that copies it.
 */
export function DeveloperHelp({ text, code, className }: { text?: string | null; code?: string | null; className?: string }) {
    const [copied, setCopied] = React.useState(false);
    if (!text && !code) return null;
    const copy = () => {
        if (!code) return;
        copyText(code).then(() => {
            setCopied(true);
            toast.success(t('Copied.'));
            window.setTimeout(() => setCopied(false), 1500);
        }, () => toast.error(t('Copying failed: select the text and copy it yourself.')));
    };

    return (
        <details className={cn('group/dev text-sm text-muted-foreground', className)}>
            <summary className="inline-flex cursor-pointer list-none items-center gap-1 rounded-sm text-xs font-medium select-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                <ChevronRight className="size-3.5 transition-transform group-open/dev:rotate-90" aria-hidden />
                {t('For developers')}
            </summary>
            <div className="mt-2 grid gap-2 border-l-2 pl-3">
                {text && <p className="whitespace-pre-line">{text}</p>}
                {code && (
                    <div className="relative">
                        <pre className="overflow-x-auto rounded-md border bg-muted/60 py-2 pr-10 pl-3 font-mono text-xs leading-relaxed text-foreground"><code>{code}</code></pre>
                        <button type="button" onClick={copy} aria-label={t('Copy')} title={t('Copy')}
                            className="absolute top-1.5 right-1.5 inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                            {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                        </button>
                    </div>
                )}
            </div>
        </details>
    );
}
