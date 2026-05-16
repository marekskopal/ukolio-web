import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Project} from '@app/models/project';
import {AlertService} from '@app/services/alert.service';
import {ProjectService} from '@app/services/project.service';

@Component({
    selector: 'tm-projects',
    standalone: true,
    imports: [RouterLink],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './projects.component.html',
    styleUrl: './projects.component.scss',
})
export class ProjectsComponent implements OnInit {
    private readonly projectService = inject(ProjectService);
    private readonly alertService = inject(AlertService);

    protected readonly loading = signal(true);
    protected readonly projects = signal<Project[]>([]);

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            this.projects.set(await this.projectService.getProjects());
        } finally {
            this.loading.set(false);
        }
    }

    protected async onDelete(project: Project): Promise<void> {
        if (!confirm(`Delete project "${project.name}"? This also removes all its tasks and history.`)) {
            return;
        }
        try {
            await this.projectService.deleteProject(project.id);
            this.alertService.success('Project deleted.');
            this.projects.update((all) => all.filter((p) => p.id !== project.id));
        } catch {
            // error interceptor
        }
    }
}
