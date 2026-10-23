import { Check, Circle } from 'lucide-react';
import { t } from '@/lib/i18n';
import { passwordChecks, type PasswordCheckKey, type PasswordRules } from '@/lib/password-rules';
import { cn } from '@/lib/utils';

function label(key: PasswordCheckKey, rules: PasswordRules): string {
    switch (key) {
        case 'length': return t(':count characters or more', { count: rules.min });
        case 'mixedCase': return t('Upper and lower case letters');
        case 'letters': return t('A letter');
        case 'numbers': return t('A number');
        case 'symbols': return t('A symbol');
    }
}

/**
 * The password rules under a new password's input, ticked as they're met.
 * Link it to the input with aria-describedby={id}: read with the input,
 * not announced on every key.
 */
export function PasswordChecklist({ id, rules, value, className }: { id: string; rules: PasswordRules | null | undefined; value: string; className?: string }) {
    const checks = passwordChecks(rules, value);
    if (!rules || checks.length === 0) return null;

    return (
        <ul id={id} className={cn('grid gap-1 text-sm text-muted-foreground', className)}>
            {checks.map((check) => (
                <li key={check.key} className={cn('flex items-center gap-2', check.met && 'text-foreground')}>
                    {check.met
                        ? <Check className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden />
                        : <Circle className="size-3.5 shrink-0" aria-hidden />}
                    <span>{label(check.key, rules)}</span>
                    <span className="sr-only">{check.met ? t('(done)') : t('(not yet)')}</span>
                </li>
            ))}
        </ul>
    );
}
