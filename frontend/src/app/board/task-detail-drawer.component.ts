import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, output, signal} from '@angular/core';
import {takeUntilDestroyed, toSignal} from '@angular/core/rxjs-interop';
import {FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {ProjectField} from '@app/models/field';
import {COMMENT_EVENT_TYPES, FILE_EVENT_TYPES, RealtimeEvent, RELATION_EVENT_TYPES} from '@app/models/realtime-event';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {Task, TaskListItem, TaskPriority} from '@app/models/task';
import {TaskComment} from '@app/models/task-comment';
import {TaskFile} from '@app/models/task-file';
import {TaskRelation, TaskRelationType} from '@app/models/task-relation';
import {WorkspaceMember} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TaskService} from '@app/services/task.service';
import {TaskCommentService} from '@app/services/task-comment.service';
import {TaskRelationService} from '@app/services/task-relation.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';
import {MarkdownComponent} from 'ngx-markdown';
import {debounceTime, distinctUntilChanged} from 'rxjs';

interface CustomControlDescriptor {
    controlName: string;
    pf: ProjectField;
    options: string[];
}

interface RelationGroup {
    key: string;
    headerKey: string;
    items: TaskRelation[];
}

interface FileTypeChip {
    tag: string;
    bg: string;
    fg: string;
}

const RELATION_TYPES: TaskRelationType[] = ['Related', 'Duplicates', 'Parent', 'DependsOn'];

const FILE_TYPE_MAP: Record<string, FileTypeChip> = {
    pdf: {tag: 'PDF', fg: '#b42318', bg: '#fdecea'},
    doc: {tag: 'DOC', fg: '#1e58b6', bg: '#e6efff'},
    docx: {tag: 'DOC', fg: '#1e58b6', bg: '#e6efff'},
    xls: {tag: 'XLS', fg: '#16794a', bg: '#e6f5ee'},
    xlsx: {tag: 'XLS', fg: '#16794a', bg: '#e6f5ee'},
    csv: {tag: 'CSV', fg: '#16794a', bg: '#e6f5ee'},
    png: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    jpg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    jpeg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    svg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    gif: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    md: {tag: 'MD', fg: '#18181b', bg: '#f4f4f5'},
    txt: {tag: 'TXT', fg: '#52525b', bg: '#f4f4f5'},
    log: {tag: 'LOG', fg: '#52525b', bg: '#f4f4f5'},
    json: {tag: 'JSON', fg: '#a35c00', bg: '#fbf2dd'},
    yaml: {tag: 'YML', fg: '#a35c00', bg: '#fbf2dd'},
    yml: {tag: 'YML', fg: '#a35c00', bg: '#fbf2dd'},
    sql: {tag: 'SQL', fg: '#0e7490', bg: '#e0f2fe'},
    zip: {tag: 'ZIP', fg: '#52525b', bg: '#ebebed'},
    mp4: {tag: 'MP4', fg: '#be185d', bg: '#fce7f3'},
    mov: {tag: 'MOV', fg: '#be185d', bg: '#fce7f3'},
};

const FILE_TYPE_FALLBACK: FileTypeChip = {tag: 'FILE', fg: '#52525b', bg: '#f4f4f5'};

@Component({
    selector: 'uk-task-detail-drawer',
    standalone: true,
    imports: [ReactiveFormsModule, MarkdownComponent, MarkdownEditorComponent, TranslatePipe, DatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-detail-drawer.component.html',
    styleUrl: './task-detail-drawer.component.scss',
})
export class TaskDetailDrawerComponent implements OnInit {
    public readonly task = input<Task | null>(null);
    public readonly statuses = input.required<Status[]>();
    public readonly projectId = input.required<number>();
    public readonly defaultStatusId = input<number | null>(null);
    public readonly projectFields = input<ProjectField[]>([]);
    public readonly workspaceTags = input<Tag[]>([]);

    public readonly saved = output<Task>();
    public readonly deleted = output<number>();
    public readonly cancelled = output<void>();
    public readonly openTask = output<TaskListItem>();

    private readonly fb = inject(FormBuilder);
    private readonly taskService = inject(TaskService);
    private readonly fieldService = inject(FieldService);
    private readonly taskRelationService = inject(TaskRelationService);
    private readonly taskCommentService = inject(TaskCommentService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);
    private readonly realtimeService = inject(RealtimeService);
    private readonly workspaceService = inject(WorkspaceService);

    protected readonly saving = signal(false);
    protected readonly descriptionInitialTab = computed<'edit' | 'preview'>(() =>
        this.task() === null ? 'edit' : 'preview',
    );

    protected readonly selectedTagIds = signal<number[]>([]);
    protected readonly tagPickerOpen = signal(false);

    protected readonly selectedTags = computed<Tag[]>(() => {
        const ids = new Set(this.selectedTagIds());
        return this.workspaceTags().filter((t) => ids.has(t.id));
    });

    protected readonly availableTags = computed<Tag[]>(() => {
        const ids = new Set(this.selectedTagIds());
        return this.workspaceTags().filter((t) => !ids.has(t.id));
    });

    protected readonly selectedAssigneeId = signal<number | null>(null);
    protected readonly assigneePickerOpen = signal(false);

    protected readonly sortedMembers = computed<WorkspaceMember[]>(() => {
        const me = this.currentUserService.currentUser()?.id;
        return [...this.workspaceService.currentMembers()].sort((a, b) => {
            if (a.userId === me && b.userId !== me) return -1;
            if (b.userId === me && a.userId !== me) return 1;
            return a.name.localeCompare(b.name);
        });
    });

    protected readonly selectedAssignee = computed<WorkspaceMember | null>(() => {
        const id = this.selectedAssigneeId();
        if (id === null) return null;
        return this.workspaceService.currentMembers().find((m) => m.userId === id) ?? null;
    });

    protected readonly files = signal<TaskFile[]>([]);
    protected readonly uploading = signal(false);

    protected readonly outgoingRelations = signal<TaskRelation[]>([]);
    protected readonly incomingRelations = signal<TaskRelation[]>([]);
    protected readonly relationsLoaded = signal(false);
    protected readonly addRelationOpen = signal(false);
    protected readonly addRelationType = signal<TaskRelationType>('Related');
    protected readonly addRelationSaving = signal(false);
    protected readonly relationTypes = RELATION_TYPES;

    protected readonly comments = signal<TaskComment[]>([]);
    protected readonly commentsLoaded = signal(false);
    protected readonly commentForm = this.fb.nonNullable.group({
        body: ['', [Validators.required, Validators.minLength(1)]],
    });
    protected readonly postingComment = signal(false);

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly searchResults = signal<TaskListItem[]>([]);
    private readonly searchTerm = toSignal(
        this.searchControl.valueChanges.pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed()),
        {initialValue: ''},
    );

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        statusId: [0, Validators.required],
        priority: ['Medium' as TaskPriority, Validators.required],
        dueDate: [''],
    });

    private readonly statusId = signal<number>(0);

    protected readonly currentStatusColor = computed<string>(() => {
        const id = this.statusId();
        const match = this.statuses().find((s) => s.id === id);
        return match?.color ?? '#94a3a8';
    });

    protected readonly customControls = computed<CustomControlDescriptor[]>(() => {
        const sorted = [...this.projectFields()].sort((a, b) => a.position - b.position);
        return sorted.map((pf) => ({
            controlName: 'field_' + pf.fieldId,
            pf,
            options: pf.field.type === 'Version'
                ? this.fieldService.sortVersionsDescending(pf.field.options ?? [])
                : pf.field.options ?? [],
        }));
    });

    protected readonly groupedRelations = computed<RelationGroup[]>(() => {
        const outgoing = this.outgoingRelations();
        const incoming = this.incomingRelations();
        const groups: RelationGroup[] = [
            {key: 'Parent', headerKey: 'app.taskRelations.groupHeader.Parent', items: incoming.filter((r) => r.type === 'Parent')},
            {key: 'Subtasks', headerKey: 'app.taskRelations.groupHeader.Subtasks', items: outgoing.filter((r) => r.type === 'Parent')},
            {key: 'DependsOn', headerKey: 'app.taskRelations.groupHeader.DependsOn', items: outgoing.filter((r) => r.type === 'DependsOn')},
            {key: 'RequiredFor', headerKey: 'app.taskRelations.groupHeader.RequiredFor', items: incoming.filter((r) => r.type === 'DependsOn')},
            {
                key: 'Related',
                headerKey: 'app.taskRelations.groupHeader.Related',
                items: [...outgoing.filter((r) => r.type === 'Related'), ...incoming.filter((r) => r.type === 'Related')],
            },
            {
                key: 'Duplicates',
                headerKey: 'app.taskRelations.groupHeader.Duplicates',
                items: [...outgoing.filter((r) => r.type === 'Duplicates'), ...incoming.filter((r) => r.type === 'Duplicates')],
            },
        ];
        return groups;
    });

    protected readonly hasAnyRelation = computed<boolean>(() =>
        this.outgoingRelations().length + this.incomingRelations().length > 0,
    );

    public constructor() {
        const trigger = computed<string>(() => this.searchTerm() ?? '');
        this.searchControl.valueChanges
            .pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe(() => {
                void this.runSearch(trigger());
            });

        this.realtimeService.events$
            .pipe(takeUntilDestroyed())
            .subscribe((event) => this.onRealtimeEvent(event));
    }

    private onRealtimeEvent(event: RealtimeEvent): void {
        const current = this.task();
        if (current === null || event.taskId !== current.id) {
            return;
        }
        if (COMMENT_EVENT_TYPES.has(event.type)) {
            void this.loadComments(current.id);
        } else if (FILE_EVENT_TYPES.has(event.type)) {
            void this.loadFiles(current.id);
        } else if (RELATION_EVENT_TYPES.has(event.type)) {
            void this.loadRelations(current.id);
        }
    }

    public ngOnInit(): void {
        if (this.workspaceService.currentMembers().length === 0) {
            void this.workspaceService.loadCurrentMembers();
        }

        const existing = this.task();
        if (existing) {
            this.form.patchValue({
                name: existing.name,
                description: existing.description ?? '',
                statusId: existing.statusId,
                priority: existing.priority,
                dueDate: existing.dueDate ?? '',
            });
            this.statusId.set(existing.statusId);
            this.selectedTagIds.set([...(existing.tagIds ?? [])]);
            this.selectedAssigneeId.set(existing.assigneeId);
            void this.loadFiles(existing.id);
            void this.loadRelations(existing.id);
            void this.loadComments(existing.id);
        } else {
            const fallbackStatusId = this.defaultStatusId() ?? this.statuses()[0]?.id ?? 0;
            this.form.patchValue({statusId: fallbackStatusId});
            this.statusId.set(fallbackStatusId);
            this.selectedAssigneeId.set(this.currentUserService.currentUser()?.id ?? null);
        }

        this.form.controls.statusId.valueChanges.subscribe((value) => {
            this.statusId.set(Number(value));
        });

        const existingValues = new Map(existing?.fieldValues.map((fv) => [fv.fieldId, fv.value ?? '']) ?? []);
        const dynamic = this.form as unknown as FormGroup;
        for (const desc of this.customControls()) {
            const initial = existingValues.get(desc.pf.fieldId) ?? desc.pf.field.defaultValue ?? '';
            const validators = desc.pf.field.required ? [Validators.required] : [];
            dynamic.addControl(desc.controlName, new FormControl<string>(initial, {nonNullable: true, validators}));
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            const firstRequiredMissing = this.customControls().find((desc) => {
                const ctrl = this.form.get(desc.controlName);
                return desc.pf.field.required && (ctrl === null || ctrl.invalid);
            });
            if (firstRequiredMissing) {
                this.alertService.error(await this.translate.instant('app.taskFields.fieldRequired', {
                    name: firstRequiredMissing.pf.field.name,
                }) as string);
            }
            return;
        }
        this.saving.set(true);
        const fieldValues = this.customControls().map((desc) => ({
            fieldId: desc.pf.fieldId,
            value: (this.form.get(desc.controlName)?.value as string | null) ?? null,
        }));
        const payload = {
            statusId: Number(this.form.value.statusId),
            name: this.form.value.name!,
            description: (this.form.value.description ?? '').trim() === '' ? null : this.form.value.description!,
            priority: this.form.value.priority as TaskPriority,
            dueDate: this.form.value.dueDate ? this.form.value.dueDate : null,
            assigneeId: this.selectedAssigneeId(),
            fieldValues,
            tagIds: this.selectedTagIds(),
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

    protected toggleTagPicker(): void {
        this.tagPickerOpen.update((v) => !v);
    }

    protected closeTagPicker(): void {
        this.tagPickerOpen.set(false);
    }

    protected addTagToTask(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.includes(tag.id) ? ids : [...ids, tag.id]);
        this.tagPickerOpen.set(false);
    }

    protected removeTagFromTask(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.filter((id) => id !== tag.id));
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected toggleAssigneePicker(): void {
        this.assigneePickerOpen.update((v) => !v);
    }

    protected assignMember(member: WorkspaceMember): void {
        this.selectedAssigneeId.set(member.userId);
        this.assigneePickerOpen.set(false);
    }

    protected clearAssignee(): void {
        this.selectedAssigneeId.set(null);
        this.assigneePickerOpen.set(false);
    }

    protected memberInitials(name: string): string {
        const parts = name.trim().split(/\s+/);
        if (parts.length === 0 || parts[0] === '') return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    protected async onFileSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        if (!file) {
            return;
        }
        const existing = this.task();
        if (!existing) {
            return;
        }
        this.uploading.set(true);
        try {
            const uploaded = await this.taskService.uploadTaskFile(existing.id, file);
            this.files.update((current) => [...current, uploaded]);
            this.alertService.success(await this.translate.instant('app.board.drawer.files.uploaded') as string);
        } catch {
            // error interceptor
        } finally {
            this.uploading.set(false);
        }
    }

    protected async onDownloadFile(file: TaskFile): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        try {
            const blob = await this.taskService.downloadTaskFile(existing.id, file.id);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = file.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch {
            // error interceptor
        }
    }

    protected async onDeleteFile(file: TaskFile): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        const message = await this.translate.instant('app.board.drawer.files.deleteConfirm', {name: file.filename}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.taskService.deleteTaskFile(existing.id, file.id);
            this.files.update((current) => current.filter((f) => f.id !== file.id));
        } catch {
            // error interceptor
        }
    }

    protected onOpenAddRelation(): void {
        this.addRelationOpen.set(true);
        this.searchControl.setValue('');
        this.searchResults.set([]);
    }

    protected onCloseAddRelation(): void {
        this.addRelationOpen.set(false);
        this.searchControl.setValue('');
        this.searchResults.set([]);
    }

    protected onTypeChange(event: Event): void {
        this.addRelationType.set((event.target as HTMLSelectElement).value as TaskRelationType);
    }

    protected async onPickTarget(target: TaskListItem): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        if (target.id === existing.id) {
            return;
        }
        this.addRelationSaving.set(true);
        try {
            await this.taskRelationService.create(existing.id, {
                targetTaskId: target.id,
                type: this.addRelationType(),
            });
            this.alertService.success(await this.translate.instant('app.taskRelations.added') as string);
            this.onCloseAddRelation();
            await this.loadRelations(existing.id);
        } catch (err) {
            const message = this.extractErrorMessage(err)
                ?? await this.translate.instant('app.taskRelations.errors.generic') as string;
            this.alertService.error(message);
        } finally {
            this.addRelationSaving.set(false);
        }
    }

    protected async onRemoveRelation(relation: TaskRelation): Promise<void> {
        const message = await this.translate.instant('app.taskRelations.removeConfirm', {name: relation.otherTaskName}) as string;
        if (!confirm(message)) {
            return;
        }
        const existing = this.task();
        if (!existing) {
            return;
        }
        try {
            await this.taskRelationService.delete(relation.id);
            this.alertService.success(await this.translate.instant('app.taskRelations.removed') as string);
            await this.loadRelations(existing.id);
        } catch {
            // error interceptor
        }
    }

    protected async onOpenRelatedTask(relation: TaskRelation): Promise<void> {
        try {
            const task = await this.taskService.getTask(relation.otherTaskId);
            const item: TaskListItem = {
                id: task.id,
                code: task.code,
                projectId: task.projectId,
                projectName: relation.otherTaskProjectName,
                statusId: task.statusId,
                status: {
                    id: relation.otherTaskStatusId,
                    workflowId: 0,
                    name: relation.otherTaskStatusName,
                    color: relation.otherTaskStatusColor,
                    position: 0,
                    type: 'Normal',
                },
                assigneeId: task.assigneeId,
                name: task.name,
                description: task.description,
                priority: task.priority,
                dueDate: task.dueDate,
                position: task.position,
                sequenceNumber: task.sequenceNumber,
                createdByAgent: task.createdByAgent,
                createdAt: task.createdAt,
                updatedAt: task.updatedAt,
                tagIds: task.tagIds,
            };
            this.openTask.emit(item);
        } catch {
            // error interceptor
        }
    }

    protected formatFileSize(size: number): string {
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1024 * 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }

    protected fileTypeChip(filename: string): FileTypeChip {
        const dot = filename.lastIndexOf('.');
        if (dot === -1 || dot === filename.length - 1) {
            return FILE_TYPE_FALLBACK;
        }
        const ext = filename.slice(dot + 1).toLowerCase();
        return FILE_TYPE_MAP[ext] ?? FILE_TYPE_FALLBACK;
    }

    protected totalRelationCount(): number {
        return this.outgoingRelations().length + this.incomingRelations().length;
    }

    private async loadFiles(taskId: number): Promise<void> {
        try {
            const list = await this.taskService.listTaskFiles(taskId);
            this.files.set(list);
        } catch {
            // ignore — task may have just been created
        }
    }

    private async loadRelations(taskId: number): Promise<void> {
        try {
            const list = await this.taskRelationService.list(taskId);
            this.outgoingRelations.set(list.outgoing);
            this.incomingRelations.set(list.incoming);
            this.relationsLoaded.set(true);
        } catch {
            this.outgoingRelations.set([]);
            this.incomingRelations.set([]);
            this.relationsLoaded.set(true);
        }
    }

    private async loadComments(taskId: number): Promise<void> {
        try {
            const list = await this.taskCommentService.list(taskId);
            this.comments.set(list);
        } catch {
            this.comments.set([]);
        } finally {
            this.commentsLoaded.set(true);
        }
    }

    protected canDeleteComment(comment: TaskComment): boolean {
        const user = this.currentUserService.currentUser();
        if (user === null) {
            return false;
        }
        return user.systemRole === 'SystemAdmin' || comment.authorId === user.id;
    }

    protected async onAddComment(): Promise<void> {
        const existing = this.task();
        if (!existing || this.commentForm.invalid) {
            return;
        }
        const body = this.commentForm.controls.body.value.trim();
        if (body === '') {
            return;
        }
        this.postingComment.set(true);
        try {
            const created = await this.taskCommentService.create(existing.id, {body});
            this.comments.update((list) => [...list, created]);
            this.commentForm.reset({body: ''});
        } catch {
            // error interceptor
        } finally {
            this.postingComment.set(false);
        }
    }

    protected async onDeleteComment(comment: TaskComment): Promise<void> {
        const message = await this.translate.instant('app.taskComments.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.taskCommentService.delete(comment.id);
            this.comments.update((list) => list.filter((c) => c.id !== comment.id));
        } catch {
            // error interceptor
        }
    }

    private async runSearch(term: string): Promise<void> {
        if (!this.addRelationOpen()) {
            return;
        }
        if (term.trim() === '') {
            this.searchResults.set([]);
            return;
        }
        try {
            const result = await this.taskService.getTasks({
                limit: 20,
                offset: 0,
                orderBy: 'name',
                orderDirection: 'ASC',
                search: term.trim(),
            });
            const currentId = this.task()?.id;
            this.searchResults.set(result.tasks.filter((t) => t.id !== currentId));
        } catch {
            this.searchResults.set([]);
        }
    }

    private extractErrorMessage(err: unknown): string | null {
        if (typeof err === 'object' && err !== null && 'error' in err) {
            const inner = (err as {error: unknown}).error;
            if (typeof inner === 'object' && inner !== null && 'message' in inner) {
                const msg = (inner as {message: unknown}).message;
                if (typeof msg === 'string' && msg !== '') {
                    return msg;
                }
            }
        }
        return null;
    }
}
