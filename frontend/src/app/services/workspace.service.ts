import {HttpClient} from '@angular/common/http';
import {inject, Injectable, signal} from '@angular/core';
import {Invitation, Workspace, WorkspaceMember} from '@app/models/workspace';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class WorkspaceService {
    private readonly http = inject(HttpClient);

    public readonly workspaces = signal<Workspace[]>([]);
    public readonly currentWorkspaceId = signal<number | null>(null);

    public async loadAll(): Promise<Workspace[]> {
        const all = await firstValueFrom(this.http.get<Workspace[]>(`${environment.apiUrl}/workspaces`));
        this.workspaces.set(all);
        return all;
    }

    public async create(name: string): Promise<Workspace> {
        const ws = await firstValueFrom(this.http.post<Workspace>(`${environment.apiUrl}/workspaces`, {name}));
        await this.loadAll();
        return ws;
    }

    public async update(id: number, name: string): Promise<Workspace> {
        const ws = await firstValueFrom(this.http.put<Workspace>(`${environment.apiUrl}/workspaces/${id}`, {name}));
        await this.loadAll();
        return ws;
    }

    public async delete(id: number): Promise<void> {
        await firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/workspaces/${id}`));
        await this.loadAll();
    }

    public async switchTo(id: number): Promise<Workspace> {
        const ws = await firstValueFrom(this.http.post<Workspace>(`${environment.apiUrl}/workspaces/${id}/switch`, {}));
        this.currentWorkspaceId.set(ws.id);
        return ws;
    }

    public getMembers(id: number): Promise<WorkspaceMember[]> {
        return firstValueFrom(this.http.get<WorkspaceMember[]>(`${environment.apiUrl}/workspaces/${id}/members`));
    }

    public removeMember(workspaceId: number, userId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/workspaces/${workspaceId}/members/${userId}`));
    }

    public getInvitations(workspaceId: number): Promise<Invitation[]> {
        return firstValueFrom(this.http.get<Invitation[]>(`${environment.apiUrl}/workspaces/${workspaceId}/invitations`));
    }

    public createInvitation(workspaceId: number, email: string, role: 'Owner' | 'Member' = 'Member'): Promise<Invitation> {
        return firstValueFrom(
            this.http.post<Invitation>(`${environment.apiUrl}/workspaces/${workspaceId}/invitations`, {email, role}),
        );
    }

    public deleteInvitation(invitationId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/invitations/${invitationId}`));
    }

    public lookupInvitation(token: string): Promise<Invitation> {
        return firstValueFrom(this.http.post<Invitation>(`${environment.apiUrl}/invitations/lookup`, {token}));
    }

    public acceptInvitation(token: string): Promise<Invitation> {
        return firstValueFrom(this.http.post<Invitation>(`${environment.apiUrl}/invitations/accept`, {token}));
    }

    public clear(): void {
        this.workspaces.set([]);
        this.currentWorkspaceId.set(null);
    }
}
