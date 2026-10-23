/**
 * The checklist under a new password: the rules the server enforces
 * (PasswordPolicy::checklist(), sent as the `passwordRules` prop), each
 * met or not by what's typed so far. Counted and matched the way PHP
 * does (characters, Unicode letter cases, numbers and symbols), so a
 * ticked list is a password the server accepts, but for the checks only
 * the server makes (the person's email or name, known data leaks).
 */
export interface PasswordRules {
    min: number;
    mixedCase: boolean;
    letters: boolean;
    numbers: boolean;
    symbols: boolean;
}

export type PasswordCheckKey = 'length' | 'mixedCase' | 'letters' | 'numbers' | 'symbols';

export interface PasswordCheck {
    key: PasswordCheckKey;
    met: boolean;
}

export function isPasswordRules(value: unknown): value is PasswordRules {
    if (value === null || typeof value !== 'object') return false;
    const rules = value as Record<string, unknown>;

    return typeof rules.min === 'number' && ['mixedCase', 'letters', 'numbers', 'symbols'].every((key) => typeof rules[key] === 'boolean');
}

/** The rules to show, in order, with whether `value` meets each; none without rules. */
export function passwordChecks(rules: PasswordRules | null | undefined, value: string): PasswordCheck[] {
    if (!rules) return [];
    const checks: PasswordCheck[] = [{ key: 'length', met: [...value].length >= rules.min }];
    if (rules.mixedCase) checks.push({ key: 'mixedCase', met: /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value) });
    if (rules.letters && !rules.mixedCase) checks.push({ key: 'letters', met: /\p{L}/u.test(value) });
    if (rules.numbers) checks.push({ key: 'numbers', met: /\p{N}/u.test(value) });
    if (rules.symbols) checks.push({ key: 'symbols', met: /[\p{Z}\p{S}\p{P}]/u.test(value) });

    return checks;
}

/** Whether every rule shown is met. */
export function meetsPasswordRules(rules: PasswordRules | null | undefined, value: string): boolean {
    return passwordChecks(rules, value).every((check) => check.met);
}
