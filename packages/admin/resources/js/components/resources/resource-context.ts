import * as React from 'react';

/** The resource whose form is shown, so relation fields know where to search. */
export const ResourceKeyContext = React.createContext<string | null>(null);
