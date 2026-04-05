import { createContext } from 'react';

/** Context for the active (double-clicked) node — consumed by node cards */
export const ActiveNodeContext = createContext<string | null>(null);
