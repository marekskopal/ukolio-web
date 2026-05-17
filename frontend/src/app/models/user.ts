export type Locale = 'en' | 'cs';

export interface User {
    id: number;
    email: string;
    name: string;
    locale: Locale;
    currentWorkspaceId: number | null;
}
