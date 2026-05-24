import {TaskFieldValue} from '@app/models/field';
import {Status} from '@app/models/status';

export type TaskPriority = 'Low' | 'Medium' | 'High';

export interface Task {
    id: number;
    code: string;
    projectId: number;
    statusId: number;
    assigneeId: number | null;
    name: string;
    description: string | null;
    priority: TaskPriority;
    dueDate: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    createdAt: string;
    updatedAt: string;
    fieldValues: TaskFieldValue[];
    tagIds: number[];
}

export type TaskOrderBy = 'created_at' | 'name' | 'status_id';
export type OrderDirection = 'ASC' | 'DESC';

export interface TaskListItem {
    id: number;
    code: string;
    projectId: number;
    projectName: string;
    statusId: number;
    status: Status;
    assigneeId: number | null;
    name: string;
    description: string | null;
    priority: TaskPriority;
    dueDate: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
}

export interface TaskList {
    tasks: TaskListItem[];
    count: number;
}
