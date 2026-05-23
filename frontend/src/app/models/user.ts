export type Locale = 'en' | 'cs';

export type SystemRole = 'User' | 'SystemAdmin';

export interface User {
    id: number;
    email: string;
    name: string;
    locale: Locale;
    currentWorkspaceId: number | null;
    systemRole: SystemRole;
    emailVerified: boolean;
    onboardingCompletedAt: string | null;
}
