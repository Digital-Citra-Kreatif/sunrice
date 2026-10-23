// Module state the entry and a lazy page share: one copy only if the page imports the same main chunk
// (a second copy would replace the list on globalThis with its own).
export const loads: string[] = [];
(globalThis as { splitLoads?: string[] }).splitLoads = loads;
