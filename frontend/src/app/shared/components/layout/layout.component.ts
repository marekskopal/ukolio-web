import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {User} from '@app/models/user';
import {Workspace} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {WorkspaceService} from '@app/services/workspace.service';

@Component({
    selector: 'uk-layout',
    standalone: true,
    imports: [RouterOutlet, RouterLink, RouterLinkActive],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './layout.component.html',
    styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit {
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly alertService = inject(AlertService);
    private readonly router = inject(Router);

    protected readonly user = signal<User | null>(null);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly currentWorkspaceId = this.workspaceService.currentWorkspaceId;
    protected readonly currentWorkspace = computed<Workspace | null>(() => {
        const id = this.currentWorkspaceId();
        return this.workspaces().find((w) => w.id === id) ?? null;
    });
    protected readonly switcherOpen = signal(false);

    public async ngOnInit(): Promise<void> {
        try {
            const user = await this.currentUserService.load();
            this.user.set(user);
            this.workspaceService.currentWorkspaceId.set(user.currentWorkspaceId);
            await this.workspaceService.loadAll();
        } catch {
            // Interceptor handles 401 -> refresh / logout
        }
    }

    protected toggleSwitcher(): void {
        this.switcherOpen.update((v) => !v);
    }

    protected async switchTo(workspaceId: number): Promise<void> {
        this.switcherOpen.set(false);
        try {
            await this.workspaceService.switchTo(workspaceId);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected async createWorkspace(): Promise<void> {
        this.switcherOpen.set(false);
        const name = prompt('New workspace name:');
        if (name === null || name.trim() === '') {
            return;
        }
        try {
            const ws = await this.workspaceService.create(name.trim());
            await this.workspaceService.switchTo(ws.id);
            this.alertService.success(`Workspace "${ws.name}" created.`);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected logout(): void {
        this.currentUserService.clear();
        this.workspaceService.clear();
        this.auth.logout();
    }
}
