/**
 * The `dir` of a text input: plain text follows what's typed (`dir="auto"`, so Arabic or Hebrew starts on the
 * right and English on the left, whatever the panel's language); addresses, emails, numbers and the like stay
 * as the panel lays them out.
 */
export function textDir(inputType: unknown): 'auto' | undefined {
    return inputType === undefined || inputType === null || inputType === 'text' || inputType === 'search' ? 'auto' : undefined;
}
