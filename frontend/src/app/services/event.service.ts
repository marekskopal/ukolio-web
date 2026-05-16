import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {AuditEvent} from '@app/models/event';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class EventService {
    private readonly http = inject(HttpClient);

    public getEvents(projectId: number, limit = 100, offset = 0): Promise<AuditEvent[]> {
        return firstValueFrom(
            this.http.get<AuditEvent[]>(`${environment.apiUrl}/projects/${projectId}/events`, {params: {limit, offset}}),
        );
    }
}
