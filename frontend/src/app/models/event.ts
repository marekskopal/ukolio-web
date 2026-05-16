export type EventType =
    | 'ProjectCreated' | 'ProjectUpdated' | 'ProjectDeleted'
    | 'WorkflowUpdated'
    | 'StatusCreated' | 'StatusUpdated' | 'StatusDeleted' | 'StatusMoved'
    | 'TaskCreated' | 'TaskUpdated' | 'TaskDeleted' | 'TaskMoved';

export interface AuditEvent {
    id: number;
    authorName: string;
    taskId: number | null;
    type: EventType;
    metadata: Record<string, unknown>;
    createdAt: string;
}
