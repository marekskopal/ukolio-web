export type TaskPriority = 'Low' | 'Medium' | 'High';

export interface Task {
    id: number;
    projectId: number;
    statusId: number;
    name: string;
    description: string | null;
    priority: TaskPriority;
    dueDate: string | null;
    position: number;
    createdAt: string;
    updatedAt: string;
}
