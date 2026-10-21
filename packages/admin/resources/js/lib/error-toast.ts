/**
 * The toast for a rejected request's errors (pages also show them inline),
 * or null without any. Its id comes from the messages: Inertia fires its
 * error event for every response that carries errors, and a page that
 * loads deferred props right after the redirect (the entry editor's
 * revisions) gets the same errors back again, so the same messages
 * update the toast already shown instead of stacking a second one.
 */
export function errorToast(
    errors: Record<string, unknown> | null | undefined,
    more: (message: string, count: number) => string = (message, count) => `${message} (+${count} more)`,
): { message: string; id: string } | null {
    const messages = Object.values(errors ?? {}).filter((message): message is string => typeof message === 'string' && message !== '');
    const [first] = messages;
    if (first === undefined) return null;

    return {
        message: messages.length === 1 ? first : more(first, messages.length - 1),
        id: `errors:${messages.join('\n')}`,
    };
}
