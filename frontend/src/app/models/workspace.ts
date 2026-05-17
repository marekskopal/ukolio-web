export interface Workspace {
    id: number;
    name: string;
    ownerId: number;
    createdAt: string;
}

export interface WorkspaceMember {
    userId: number;
    name: string;
    email: string;
    role: 'Owner' | 'Member';
}

export interface Invitation {
    id: number;
    workspaceId: number;
    workspaceName: string;
    email: string;
    inviterName: string;
    role: 'Owner' | 'Member';
    expiresAt: string;
    acceptedAt: string | null;
}
