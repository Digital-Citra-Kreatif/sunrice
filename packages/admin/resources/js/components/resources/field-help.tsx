import { ArrowUpRight } from 'lucide-react';
import { t } from '@/lib/i18n';
import { codeParts, developerText, type FormLink, type HelpValue, liveHelp, visibleLinks } from '@/lib/live-help';
import type { FieldInfo } from './types';

/** A text with its `backtick` parts as code. */
function WithCode({ text }: { text: string }) {
    return codeParts(text).map(([part, code], i) => code ? <code key={i} className="rounded bg-muted px-1 py-0.5 font-mono text-[0.8125rem] break-words text-foreground">{part}</code> : part);
}

/**
 * What a form field says under its input: its help (with the form's values filled in, Field::helpValues()),
 * a "For developers:" line (Field::developerHelp()), a disclosure with more (Field::helpDetails()) and links
 * (Field::links()).
 */
export function FieldHelp({ field, data }: { field: FieldInfo; data: Record<string, unknown> }) {
    const developer = field.developerHelp as { text: string; example: string | null } | undefined;
    const details = field.helpDetails as { summary: string; text: string } | undefined;
    const links = visibleLinks(field.links as FormLink[] | undefined, data);

    return (
        <>
            {field.help && <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">{liveHelp(field.help, field.helpValues as Record<string, HelpValue> | undefined, data)}</p>}
            {developer && (
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium">{t('For developers:')}</span>{' '}
                    <WithCode text={developerText(developer.text, data[field.name], developer.example)} />
                </p>
            )}
            {details && (
                <details className="text-sm text-muted-foreground">
                    <summary className="w-fit cursor-pointer rounded-sm select-none underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring">{details.summary}</summary>
                    <p className="mt-1.5 whitespace-pre-line"><WithCode text={details.text} /></p>
                </details>
            )}
            {links.length > 0 && <FormLinks links={links} />}
        </>
    );
}

/** Links in a row (Link): those opening a new tab are marked ↗. */
export function FormLinks({ links, className }: { links: FormLink[]; className?: string }) {
    return (
        <div className={className ?? 'flex flex-wrap gap-x-4 gap-y-1 text-sm'}>
            {links.map((link) => (
                <a key={link.label} href={link.url} className="inline-flex items-center gap-0.5 font-medium text-primary underline-offset-4 hover:underline"
                    {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
                    {link.label}
                    {link.newTab && <><ArrowUpRight aria-hidden className="size-3.5" /><span className="sr-only"> {t('(opens in a new tab)')}</span></>}
                </a>
            ))}
        </div>
    );
}

/** A section's header links (Section::links()), shown by the form's values. */
export function SectionLinks({ links, data }: { links: FormLink[] | undefined; data: Record<string, unknown> }) {
    const shown = visibleLinks(links, data);

    return shown.length > 0 ? <FormLinks links={shown} className="flex flex-wrap justify-end gap-x-4 gap-y-1 text-sm" /> : null;
}
