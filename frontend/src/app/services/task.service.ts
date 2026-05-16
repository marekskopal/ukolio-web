import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Task, TaskPriority} from '@app/models/task';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface TaskWritePayload {
    statusId: number;
    name: string;
    description: string | null;
    priority: TaskPriority;
    dueDate: string | null;
}

@Injectable({providedIn: 'root'})
export class TaskService {
    private readonly http = inject(HttpClient);

    public createTask(projectId: number, payload: TaskWritePayload): Promise<Task> {
        return firstValueFrom(this.http.post<Task>(`${environment.apiUrl}/projects/${projectId}/tasks`, payload));
    }

    public updateTask(taskId: number, payload: TaskWritePayload): Promise<Task> {
        return firstValueFrom(this.http.put<Task>(`${environment.apiUrl}/tasks/${taskId}`, payload));
    }

    public moveTask(taskId: number, statusId: number, position: number): Promise<Task> {
        return firstValueFrom(this.http.put<Task>(`${environment.apiUrl}/tasks/${taskId}/move`, {statusId, position}));
    }

    public deleteTask(taskId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/tasks/${taskId}`));
    }
}
