export type RealtimeEventType =
    | 'TaskCreated'
    | 'TaskUpdated'
    | 'TaskMoved'
    | 'TaskDeleted'
    | 'TaskTagsUpdated'
    | 'TaskCommentAdded'
    | 'TaskCommentDeleted'
    | 'TaskFileAdded'
    | 'TaskFileDeleted'
    | 'TaskRelationCreated'
    | 'TaskRelationDeleted'
    | 'RealtimeReconnected';

export interface RealtimeEvent {
    type: RealtimeEventType;
    workspaceId: number;
    projectId: number | null;
    taskId: number | null;
    commentId: number | null;
    fileId: number | null;
    relationId: number | null;
    originClientId: string | null;
}

export const TASK_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskCreated',
    'TaskUpdated',
    'TaskMoved',
    'TaskDeleted',
    'TaskTagsUpdated',
    'TaskFileAdded',
    'TaskFileDeleted',
    'TaskRelationCreated',
    'TaskRelationDeleted',
]);

export const COMMENT_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskCommentAdded',
    'TaskCommentDeleted',
]);

export const FILE_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskFileAdded',
    'TaskFileDeleted',
]);

export const RELATION_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskRelationCreated',
    'TaskRelationDeleted',
]);
