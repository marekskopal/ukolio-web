import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {AuditEvent} from '@app/models/event';
import {EventService} from '@app/services/event.service';

@Component({
    selector: 'tm-events',
    standalone: true,
    imports: [RouterLink],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './events.component.html',
    styleUrl: './events.component.scss',
})
export class EventsComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly eventService = inject(EventService);

    protected readonly loading = signal(true);
    protected readonly events = signal<AuditEvent[]>([]);
    protected readonly projectId = signal<number | null>(null);

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.projectId.set(id);
        try {
            this.events.set(await this.eventService.getEvents(id));
        } finally {
            this.loading.set(false);
        }
    }

    protected describe(event: AuditEvent): string {
        const md = event.metadata;
        switch (event.type) {
            case 'ProjectCreated': return `created the project`;
            case 'ProjectUpdated': return `updated the project`;
            case 'ProjectDeleted': return `deleted the project`;
            case 'WorkflowUpdated': return `updated the workflow`;
            case 'StatusCreated': return `added status "${String(md['name'] ?? '')}"`;
            case 'StatusUpdated': return `updated a status`;
            case 'StatusDeleted': return `deleted a status`;
            case 'StatusMoved': return `reordered statuses`;
            case 'TaskCreated': return `created task "${String(md['name'] ?? '')}"`;
            case 'TaskUpdated': return `updated task "${String(md['name'] ?? '')}"`;
            case 'TaskDeleted': return `deleted task "${String(md['name'] ?? '')}"`;
            case 'TaskMoved':
                return `moved "${String(md['taskName'] ?? '')}" from ${String(md['fromStatusName'] ?? '?')} → ${String(md['toStatusName'] ?? '?')}`;
        }
    }

    protected formatRelative(iso: string): string {
        const then = new Date(iso).getTime();
        const diff = Math.max(0, Date.now() - then);
        const s = Math.round(diff / 1000);
        if (s < 60) return `${s}s ago`;
        const m = Math.round(s / 60);
        if (m < 60) return `${m}m ago`;
        const h = Math.round(m / 60);
        if (h < 24) return `${h}h ago`;
        return new Date(iso).toLocaleDateString();
    }
}
