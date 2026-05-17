import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, output, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {Status} from '@app/models/status';
import {Task, TaskPriority} from '@app/models/task';
import {AlertService} from '@app/services/alert.service';
import {TaskService} from '@app/services/task.service';
import {MarkdownComponent} from 'ngx-markdown';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-task-detail-drawer',
    standalone: true,
    imports: [ReactiveFormsModule, MarkdownComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-detail-drawer.component.html',
    styleUrl: './task-detail-drawer.component.scss',
})
export class TaskDetailDrawerComponent implements OnInit {
    public readonly task = input<Task | null>(null);
    public readonly statuses = input.required<Status[]>();
    public readonly projectId = input.required<number>();
    public readonly defaultStatusId = input<number | null>(null);

    public readonly saved = output<Task>();
    public readonly deleted = output<number>();
    public readonly cancelled = output<void>();

    private readonly fb = inject(FormBuilder);
    private readonly taskService = inject(TaskService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);
    protected readonly tab = signal<'edit' | 'preview'>('edit');

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        statusId: [0, Validators.required],
        priority: ['Medium' as TaskPriority, Validators.required],
        dueDate: [''],
    });

    protected readonly description = computed(() => this.form.controls.description.value ?? '');

    public ngOnInit(): void {
        const existing = this.task();
        if (existing) {
            this.form.patchValue({
                name: existing.name,
                description: existing.description ?? '',
                statusId: existing.statusId,
                priority: existing.priority,
                dueDate: existing.dueDate ?? '',
            });
        } else {
            const fallbackStatusId = this.defaultStatusId() ?? this.statuses()[0]?.id ?? 0;
            this.form.patchValue({statusId: fallbackStatusId});
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const payload = {
            statusId: Number(this.form.value.statusId),
            name: this.form.value.name!,
            description: (this.form.value.description ?? '').trim() === '' ? null : this.form.value.description!,
            priority: this.form.value.priority as TaskPriority,
            dueDate: this.form.value.dueDate ? this.form.value.dueDate : null,
        };
        try {
            const existing = this.task();
            const saved = existing
                ? await this.taskService.updateTask(existing.id, payload)
                : await this.taskService.createTask(this.projectId(), payload);
            this.alertService.success(
                await this.translate.instant(existing ? 'app.board.taskUpdated' : 'app.board.taskCreated') as string,
            );
            this.saved.emit(saved);
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.board.deleteTaskConfirm', {name: existing.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.taskService.deleteTask(existing.id);
            this.alertService.success(await this.translate.instant('app.board.taskDeleted') as string);
            this.deleted.emit(existing.id);
        } catch {
            // error interceptor
        }
    }

    protected onCancel(): void {
        this.cancelled.emit();
    }
}
