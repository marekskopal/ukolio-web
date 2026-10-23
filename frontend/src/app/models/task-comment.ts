export interface TaskComment {
    id: number;
    taskId: number;
    authorId: number;
    authorName: string;
    authorAvatarUrl: string | null;
    body: string;
    createdByAgent: boolean;
    mcpClientId: string | null;
    mcpClientName: string | null;
    parentCommentId: number | null;
    edited: boolean;
    createdAt: string;
}
