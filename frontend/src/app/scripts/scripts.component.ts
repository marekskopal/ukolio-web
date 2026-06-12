import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Script} from '@app/models/script';
import {AlertService} from '@app/services/alert.service';
import {PermissionsService} from '@app/services/permissions.service';
import {ScriptService} from '@app/services/script.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-scripts',
    standalone: true,
    imports: [DatePipe, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './scripts.component.html',
    styleUrl: './scripts.component.scss',
})
export class ScriptsComponent {
    private readonly scriptService = inject(ScriptService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly permissions = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly scripts = signal<Script[]>([]);
    protected readonly loading = signal(true);
    protected readonly running = signal<number | null>(null);

    protected readonly canManage = computed<boolean>(() => this.permissions.canManageScripts(this.workspaceService.currentMembers()));

    public constructor() {
        effect(() => {
            const workspaceId = this.workspaceService.currentWorkspaceId();
            if (workspaceId === null) {
                return;
            }
            void this.workspaceService.loadCurrentMembers();
            void this.load(workspaceId);
        });
    }

    protected async run(script: Script): Promise<void> {
        this.running.set(script.id);
        try {
            await this.scriptService.runScript(script.id);
            this.alertService.success(this.translate.instant('app.scripts.runQueued') as string);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.running.set(null);
        }
    }

    protected async remove(script: Script): Promise<void> {
        const message = this.translate.instant('app.scripts.deleteConfirm', {name: script.name}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.scriptService.deleteScript(script.id);
            this.scripts.update((scripts) => scripts.filter((s) => s.id !== script.id));
        } catch {
            // error interceptor surfaces the message
        }
    }

    private async load(workspaceId: number): Promise<void> {
        this.loading.set(true);
        try {
            this.scripts.set(await this.scriptService.listScripts(workspaceId));
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.loading.set(false);
        }
    }
}
