import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {Script, ScriptRun, ScriptTrigger, ScriptWritePayload} from '@app/models/script';
import {AlertService} from '@app/services/alert.service';
import {ScriptService} from '@app/services/script.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

const DEFAULT_SOURCE = '// Automate with the `ukolio` API. Example:\n'
    + '// const tasks = ukolio.tasks.list({ onlyActive: true });\n'
    + '// ukolio.log(`open tasks: ${tasks.length}`);\n';

interface EventOption {
    type: string;
    labelKey: string;
}

@Component({
    selector: 'uk-script-edit',
    standalone: true,
    imports: [DatePipe, ReactiveFormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './script-edit.component.html',
    styleUrl: './script-edit.component.scss',
})
export class ScriptEditComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly scriptService = inject(ScriptService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly scriptId = signal<number | null>(null);
    protected readonly saving = signal(false);
    protected readonly running = signal(false);
    protected readonly runs = signal<ScriptRun[]>([]);
    protected readonly trigger = signal<ScriptTrigger>('Manual');
    protected readonly selectedEvents = signal<string[]>([]);

    protected readonly isEdit = computed<boolean>(() => this.scriptId() !== null);

    protected readonly form = this.fb.nonNullable.group({
        name: ['', [Validators.required, Validators.maxLength(120)]],
        cron: ['0 9 * * 1'],
        active: [true],
        source: [DEFAULT_SOURCE, Validators.required],
    });

    protected readonly triggers: ScriptTrigger[] = ['Manual', 'Scheduled', 'Event'];
    protected readonly eventOptions: EventOption[] = [
        {type: 'TaskCreated', labelKey: 'app.scripts.events.TaskCreated'},
        {type: 'TaskUpdated', labelKey: 'app.scripts.events.TaskUpdated'},
        {type: 'TaskMoved', labelKey: 'app.scripts.events.TaskMoved'},
        {type: 'TaskDeleted', labelKey: 'app.scripts.events.TaskDeleted'},
        {type: 'TaskCommentAdded', labelKey: 'app.scripts.events.TaskCommentAdded'},
    ];

    public ngOnInit(): void {
        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam !== null) {
            const id = Number(idParam);
            this.scriptId.set(id);
            void this.load(id);
        }
    }

    protected onTriggerChange(value: string): void {
        this.trigger.set(value as ScriptTrigger);
    }

    protected toggleEvent(type: string): void {
        this.selectedEvents.update((events) => (events.includes(type) ? events.filter((e) => e !== type) : [...events, type]));
    }

    protected isEventSelected(type: string): boolean {
        return this.selectedEvents().includes(type);
    }

    protected async save(): Promise<void> {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        const payload = this.buildPayload();
        this.saving.set(true);
        try {
            const id = this.scriptId();
            if (id !== null) {
                await this.scriptService.updateScript(id, payload);
            } else {
                const workspaceId = this.workspaceService.currentWorkspaceId();
                if (workspaceId === null) {
                    return;
                }
                await this.scriptService.createScript(workspaceId, payload);
            }
            this.alertService.success(this.translate.instant('app.scripts.saved') as string);
            await this.router.navigate(['/settings/scripts']);
        } catch {
            // error interceptor surfaces validation messages (e.g. invalid cron)
        } finally {
            this.saving.set(false);
        }
    }

    protected async runNow(): Promise<void> {
        const id = this.scriptId();
        if (id === null) {
            return;
        }
        this.running.set(true);
        try {
            await this.scriptService.runScript(id);
            this.alertService.success(this.translate.instant('app.scripts.runQueued') as string);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.running.set(false);
        }
    }

    protected async refreshRuns(): Promise<void> {
        const id = this.scriptId();
        if (id === null) {
            return;
        }
        try {
            this.runs.set(await this.scriptService.listRuns(id));
        } catch {
            // error interceptor surfaces the message
        }
    }

    private buildPayload(): ScriptWritePayload {
        const trigger = this.trigger();
        let triggerConfig: string | null = null;
        if (trigger === 'Scheduled') {
            triggerConfig = this.form.controls.cron.value.trim();
        } else if (trigger === 'Event') {
            triggerConfig = JSON.stringify(this.selectedEvents());
        }

        return {
            name: this.form.controls.name.value.trim(),
            source: this.form.controls.source.value,
            trigger,
            triggerConfig,
            active: this.form.controls.active.value,
        };
    }

    private async load(id: number): Promise<void> {
        try {
            const script = await this.scriptService.getScript(id);
            this.applyScript(script);
            await this.refreshRuns();
        } catch {
            // error interceptor surfaces the message
        }
    }

    private applyScript(script: Script): void {
        this.trigger.set(script.trigger);
        this.form.patchValue({
            name: script.name,
            active: script.active,
            source: script.source,
            cron: script.trigger === 'Scheduled' ? (script.triggerConfig ?? '') : this.form.controls.cron.value,
        });
        if (script.trigger === 'Event') {
            this.selectedEvents.set(this.parseEvents(script.triggerConfig));
        }
    }

    private parseEvents(triggerConfig: string | null): string[] {
        if (triggerConfig === null) {
            return [];
        }
        try {
            const parsed: unknown = JSON.parse(triggerConfig);
            return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
        } catch {
            return [];
        }
    }
}
