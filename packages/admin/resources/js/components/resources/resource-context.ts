import * as React from 'react';

/** The address of the resource whose form is shown ("cms/collections"), so relation fields know where to search. */
export const ResourcePathContext = React.createContext<string | null>(null);
