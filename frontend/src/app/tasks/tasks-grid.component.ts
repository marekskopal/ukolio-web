import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, HostListener, inject, OnInit, signal, viewChild} from '@angular/core';
import {takeUntilDestroyed, toSignal} from '@angular/core/rxjs-interop';
import {FormControl, ReactiveFormsModule} from '@angular/forms';
import {TaskDetailDrawerComponent} from '@app/board/task-detail-drawer.component';
import {ProjectField} from '@app/models/field';
import {Priority} from '@app/models/priority';
import {RealtimeEvent, TASK_EVENT_TYPES} from '@app/models/realtime-event';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {OrderDirection, Task, TaskListItem, TaskOrderBy} from '@app/models/task';
import {WorkflowWithStatuses} from '@app/models/workflow';
import {WorkspaceMember} from '@app/models/workspace';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {PriorityService} from '@app/services/priority.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TagService} from '@app/services/tag.service';
import {BulkOp, BulkResult, TaskService} from '@app/services/task.service';
import {WorkflowService} from '@app/services/workflow.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {PaginationComponent} from '@app/shared/components/pagination/pagination.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';
import {debounceTime, distinctUntilChanged} from 'rxjs';

interface DrawerContext {
    task: Task;
    statuses: Status[];
    projectId: number;
    projectFields: ProjectField[];
}

interface QueryParams {
    limit: number;
    offset: number;
    orderBy: TaskOrderBy;
    orderDirection: OrderDirection;
    search: string | undefined;
    statusIds: number[] | undefined;
    tagIds: number[] | undefined;
    assigneeIds: number[] | undefined;
    onlyActive: boolean | undefined;
}

@Component({
    selector: 'uk-tasks-grid',
    standalone: true,
    imports: [ReactiveFormsModule, PaginationComponent, TaskDetailDrawerComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './tasks-grid.component.html',
    styleUrl: './tasks-grid.component.scss',
})
export class TasksGridComponent implements OnInit {
    private readonly taskService = inject(TaskService);
    private readonly workflowService = inject(WorkflowService);
    private readonly boardService = inject(BoardService);
    private readonly fieldService = inject(FieldService);
    private readonly tagService = inject(TagService);
    private readonly priorityService = inject(PriorityService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly realtimeService = inject(RealtimeService);
    private readonly translate = inject(TranslateService);

    private refreshTimer: ReturnType<typeof setTimeout> | null = null;

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly search = toSignal(
        this.searchControl.valueChanges.pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed()),
        {initialValue: ''},
    );

    protected readonly selectedStatusIds = signal<number[]>([]);
    protected readonly selectedTagIds = signal<number[]>([]);
    protected readonly selectedAssigneeIds = signal<number[]>([]);
    protected readonly workspaceTags = signal<Tag[]>([]);
    protected readonly priorities = signal<Priority[]>([]);
    protected readonly members = this.workspaceService.currentMembers;
    protected readonly onlyActive = signal<boolean>(false);
    protected readonly sortBy = signal<TaskOrderBy>('created_at');
    protected readonly sortDirection = signal<OrderDirection>('DESC');
    protected readonly page = signal<number>(1);
    protected readonly pageSize = signal<number>(50);

    protected readonly tasks = signal<TaskListItem[]>([]);
    protected readonly count = signal<number>(0);
    protected readonly loading = signal<boolean>(false);
    protected readonly workflows = signal<WorkflowWithStatuses[]>([]);

    protected readonly drawer = signal<DrawerContext | null>(null);

    // Bulk-selection state. Kept as a plain Set for cheap membership tests; signal value is the set ref.
    protected readonly selectedIds = signal<Set<number>>(new Set());
    protected readonly bulkBusy = signal<boolean>(false);
    protected readonly lastBulkResult = signal<BulkResult | null>(null);
    protected readonly bulkDetailsOpen = signal<boolean>(false);

    private readonly statusDetails = viewChild<ElementRef<HTMLDetailsElement>>('statusDetails');
    private readonly tagDetails = viewChild<ElementRef<HTMLDetailsElement>>('tagDetails');
    private readonly assigneeDetails = viewChild<ElementRef<HTMLDetailsElement>>('assigneeDetails');
    private readonly bulkMoveDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkMoveDetails');
    private readonly bulkAddTagDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkAddTagDetails');
    private readonly bulkRemoveTagDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkRemoveTagDetails');
    private readonly bulkAssignDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkAssignDetails');
    private readonly bulkPriorityDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkPriorityDetails');

    protected readonly tagById = computed<Map<number, Tag>>(() => {
        return new Map(this.workspaceTags().map((t) => [t.id, t]));
    });

    protected readonly memberById = computed<Map<number, WorkspaceMember>>(() => {
        return new Map(this.members().map((m) => [m.userId, m]));
    });

    protected readonly sortedMembers = computed<WorkspaceMember[]>(() => {
        const me = this.currentUserService.currentUser()?.id;
        return [...this.members()].sort((a, b) => {
            if (a.userId === me && b.userId !== me) return -1;
            if (b.userId === me && a.userId !== me) return 1;
            return a.name.localeCompare(b.name);
        });
    });

    protected readonly offset = computed<number>(() => (this.page() - 1) * this.pageSize());

    protected readonly selectionCount = computed<number>(() => this.selectedIds().size);

    protected readonly pageIds = computed<number[]>(() => this.tasks().map((t) => t.id));

    protected readonly allOnPageSelected = computed<boolean>(() => {
        const ids = this.pageIds();
        if (ids.length === 0) return false;
        const sel = this.selectedIds();
        return ids.every((id) => sel.has(id));
    });

    protected readonly someOnPageSelected = computed<boolean>(() => {
        const ids = this.pageIds();
        if (ids.length === 0) return false;
        const sel = this.selectedIds();
        return ids.some((id) => sel.has(id)) && !this.allOnPageSelected();
    });

    private readonly queryParams = computed<QueryParams>(() => ({
        limit: this.pageSize(),
        offset: this.offset(),
        orderBy: this.sortBy(),
        orderDirection: this.sortDirection(),
        search: this.search() === '' ? undefined : this.search(),
        statusIds: this.selectedStatusIds().length > 0 ? this.selectedStatusIds() : undefined,
        tagIds: this.selectedTagIds().length > 0 ? this.selectedTagIds() : undefined,
        assigneeIds: this.selectedAssigneeIds().length > 0 ? this.selectedAssigneeIds() : undefined,
        onlyActive: this.onlyActive() ? true : undefined,
    }));

    public ngOnInit(): void {
        void this.loadWorkflows();
        void this.loadWorkspaceTags();
        void this.loadWorkspacePriorities();
        if (this.workspaceService.currentMembers().length === 0) {
            void this.workspaceService.loadCurrentMembers();
        }
    }

    public constructor() {
        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe(() => this.page.set(1));

        effect(() => {
            const params = this.queryParams();
            void this.fetchTasks(params);
        });

        this.realtimeService.events$
            .pipe(takeUntilDestroyed())
            .subscribe((event) => this.onRealtimeEvent(event));
    }

    private onRealtimeEvent(event: RealtimeEvent): void {
        if (event.type !== 'RealtimeReconnected' && !TASK_EVENT_TYPES.has(event.type)) {
            return;
        }
        if (this.refreshTimer !== null) {
            clearTimeout(this.refreshTimer);
        }
        this.refreshTimer = setTimeout(() => {
            this.refreshTimer = null;
            void this.fetchTasks(this.queryParams());
        }, 150);
    }

    private async loadWorkflows(): Promise<void> {
        try {
            this.workflows.set(await this.workflowService.getWorkflows());
        } catch {
            this.workflows.set([]);
        }
    }

    private async loadWorkspaceTags(): Promise<void> {
        const workspaceId = await this.resolveCurrentWorkspaceId();
        if (workspaceId === null) {
            this.workspaceTags.set([]);
            return;
        }
        try {
            this.workspaceTags.set(await this.tagService.loadWorkspaceTags(workspaceId));
        } catch {
            this.workspaceTags.set([]);
        }
    }

    private async loadWorkspacePriorities(): Promise<void> {
        const workspaceId = await this.resolveCurrentWorkspaceId();
        if (workspaceId === null) {
            this.priorities.set([]);
            return;
        }
        try {
            this.priorities.set(await this.priorityService.loadWorkspacePriorities(workspaceId));
        } catch {
            this.priorities.set([]);
        }
    }

    private async resolveCurrentWorkspaceId(): Promise<number | null> {
        let workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            try {
                workspaceId = (await this.currentUserService.load()).currentWorkspaceId;
            } catch {
                workspaceId = null;
            }
        }
        return workspaceId;
    }

    private async fetchTasks(params: QueryParams): Promise<void> {
        this.loading.set(true);
        try {
            const result = await this.taskService.getTasks({
                limit: params.limit,
                offset: params.offset,
                orderBy: params.orderBy,
                orderDirection: params.orderDirection,
                search: params.search,
                statusIds: params.statusIds,
                tagIds: params.tagIds,
                assigneeIds: params.assigneeIds,
                onlyActive: params.onlyActive,
            });
            this.tasks.set(result.tasks);
            this.count.set(result.count);
            this.pruneSelection();
        } catch {
            this.tasks.set([]);
            this.count.set(0);
        } finally {
            this.loading.set(false);
        }
    }

    private pruneSelection(): void {
        const sel = this.selectedIds();
        if (sel.size === 0) return;
        const visible = new Set(this.tasks().map((t) => t.id));
        let changed = false;
        const next = new Set<number>();
        for (const id of sel) {
            if (visible.has(id)) {
                next.add(id);
            } else {
                changed = true;
            }
        }
        if (changed) {
            this.selectedIds.set(next);
        }
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        if (!(target instanceof Node)) {
            return;
        }
        const refs = [
            this.statusDetails(), this.tagDetails(), this.assigneeDetails(),
            this.bulkMoveDetails(), this.bulkAddTagDetails(), this.bulkRemoveTagDetails(),
            this.bulkAssignDetails(), this.bulkPriorityDetails(),
        ];
        for (const ref of refs) {
            const el = ref?.nativeElement;
            if (el?.open && !el.contains(target)) {
                el.open = false;
            }
        }
    }

    protected onSortClick(column: TaskOrderBy): void {
        if (this.sortBy() === column) {
            this.sortDirection.set(this.sortDirection() === 'ASC' ? 'DESC' : 'ASC');
        } else {
            this.sortBy.set(column);
            this.sortDirection.set(column === 'created_at' ? 'DESC' : 'ASC');
        }
        this.page.set(1);
    }

    protected sortArrow(column: TaskOrderBy): string {
        if (this.sortBy() !== column) {
            return '';
        }
        return this.sortDirection() === 'ASC' ? '↑' : '↓';
    }

    protected onStatusToggle(statusId: number, event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const current = this.selectedStatusIds();
        if (checked && !current.includes(statusId)) {
            this.selectedStatusIds.set([...current, statusId]);
        } else if (!checked) {
            this.selectedStatusIds.set(current.filter((id) => id !== statusId));
        }
        this.page.set(1);
    }

    protected isStatusSelected(statusId: number): boolean {
        return this.selectedStatusIds().includes(statusId);
    }

    protected onTagToggle(tagId: number, event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const current = this.selectedTagIds();
        if (checked && !current.includes(tagId)) {
            this.selectedTagIds.set([...current, tagId]);
        } else if (!checked) {
            this.selectedTagIds.set(current.filter((id) => id !== tagId));
        }
        this.page.set(1);
    }

    protected isTagSelected(tagId: number): boolean {
        return this.selectedTagIds().includes(tagId);
    }

    protected onAssigneeToggle(userId: number, event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const current = this.selectedAssigneeIds();
        if (checked && !current.includes(userId)) {
            this.selectedAssigneeIds.set([...current, userId]);
        } else if (!checked) {
            this.selectedAssigneeIds.set(current.filter((id) => id !== userId));
        }
        this.page.set(1);
    }

    protected isAssigneeSelected(userId: number): boolean {
        return this.selectedAssigneeIds().includes(userId);
    }

    protected onOnlyActiveToggle(event: Event): void {
        this.onlyActive.set((event.target as HTMLInputElement).checked);
        this.page.set(1);
    }

    protected clearFilters(): void {
        this.searchControl.setValue('');
        this.selectedStatusIds.set([]);
        this.selectedTagIds.set([]);
        this.selectedAssigneeIds.set([]);
        this.onlyActive.set(false);
        this.page.set(1);
    }

    protected memberName(userId: number | null): string {
        if (userId === null) return '';
        return this.memberById().get(userId)?.name ?? '';
    }

    protected memberInitials(name: string): string {
        const parts = name.trim().split(/\s+/);
        if (parts.length === 0 || parts[0] === '') return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    protected tagsForTask(taskTagIds: number[] | undefined): Tag[] {
        if (!taskTagIds || taskTagIds.length === 0) {
            return [];
        }
        const byId = this.tagById();
        const tags: Tag[] = [];
        for (const id of taskTagIds) {
            const t = byId.get(id);
            if (t) tags.push(t);
        }
        return tags;
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected onPageChange(page: number): void {
        this.page.set(page);
    }

    protected onPageSizeChange(size: number): void {
        this.pageSize.set(size);
        this.page.set(1);
    }

    // ─── Bulk selection ────────────────────────────────────────

    protected isRowSelected(id: number): boolean {
        return this.selectedIds().has(id);
    }

    protected toggleRow(id: number, event: Event): void {
        event.stopPropagation();
        const next = new Set(this.selectedIds());
        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }
        this.selectedIds.set(next);
    }

    protected togglePage(event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const ids = this.pageIds();
        const next = new Set(this.selectedIds());
        if (checked) {
            for (const id of ids) {
                next.add(id);
            }
        } else {
            for (const id of ids) {
                next.delete(id);
            }
        }
        this.selectedIds.set(next);
    }

    protected clearSelection(): void {
        this.selectedIds.set(new Set());
    }

    private get selectedIdsArray(): number[] {
        return Array.from(this.selectedIds());
    }

    protected async onBulkMove(statusId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('move', {statusId});
    }

    protected async onBulkAddTag(tagId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('tag', {tagIds: [tagId]});
    }

    protected async onBulkRemoveTag(tagId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('untag', {tagIds: [tagId]});
    }

    protected async onBulkAssign(userId: number | null): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('assign', {assigneeId: userId});
    }

    protected async onBulkPriority(priorityId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('priority', {priorityId});
    }

    protected async onBulkDelete(): Promise<void> {
        this.closeBulkPopovers();
        const count = this.selectionCount();
        const msg = await this.translate
            .get('app.tasks.bulk.confirmDelete', {count})
            .toPromise();
        if (!confirm(typeof msg === 'string' ? msg : `Delete ${count} task(s)?`)) {
            return;
        }
        await this.runBulk('delete');
    }

    private async runBulk(op: BulkOp, payload?: Record<string, unknown>): Promise<void> {
        const ids = this.selectedIdsArray;
        if (ids.length === 0) {
            return;
        }
        this.bulkBusy.set(true);
        try {
            const result = await this.taskService.bulkUpdate(ids, op, payload);
            this.lastBulkResult.set(result);

            // If the drawer is open on a deleted task, close it.
            if (op === 'delete') {
                const drawerTaskId = this.drawer()?.task.id;
                if (drawerTaskId !== undefined && result.succeeded.includes(drawerTaskId)) {
                    this.closeDrawer();
                }
            }

            // Remove succeeded-and-now-gone ids from selection optimistically; fetchTasks will prune the rest.
            if (op === 'delete') {
                const next = new Set(this.selectedIds());
                for (const id of result.succeeded) {
                    next.delete(id);
                }
                this.selectedIds.set(next);
            }

            await this.fetchTasks(this.queryParams());
        } catch {
            // error interceptor will surface failure
        } finally {
            this.bulkBusy.set(false);
        }
    }

    protected dismissBulkResult(): void {
        this.lastBulkResult.set(null);
        this.bulkDetailsOpen.set(false);
    }

    protected toggleBulkDetails(): void {
        this.bulkDetailsOpen.update((v) => !v);
    }

    private closeBulkPopovers(): void {
        const refs = [
            this.bulkMoveDetails(),
            this.bulkAddTagDetails(),
            this.bulkRemoveTagDetails(),
            this.bulkAssignDetails(),
            this.bulkPriorityDetails(),
        ];
        for (const ref of refs) {
            const el = ref?.nativeElement;
            if (el) el.open = false;
        }
    }

    // ─── Drawer + rows ─────────────────────────────────────────

    protected async onRowClick(row: TaskListItem): Promise<void> {
        await this.openTaskById(row.id, row.projectId);
    }

    protected async onOpenRelatedTask(item: TaskListItem): Promise<void> {
        // Close + reopen so the drawer re-initializes for the new task.
        this.drawer.set(null);
        await this.openTaskById(item.id, item.projectId);
    }

    private async openTaskById(taskId: number, projectId: number): Promise<void> {
        try {
            const [task, board, fields] = await Promise.all([
                this.taskService.getTask(taskId),
                this.boardService.getBoard(projectId),
                this.fieldService.listProjectFields(projectId).catch(() => [] as ProjectField[]),
            ]);
            this.drawer.set({
                task,
                statuses: board.statuses,
                projectId,
                projectFields: fields,
            });
        } catch {
            // error interceptor will surface failure
        }
    }

    protected closeDrawer(): void {
        this.drawer.set(null);
    }

    protected onTaskSaved(): void {
        this.closeDrawer();
        void this.fetchTasks(this.queryParams());
    }

    protected onTaskDeleted(): void {
        this.closeDrawer();
        void this.fetchTasks(this.queryParams());
    }

    protected formatCreated(iso: string): string {
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return iso;
        }
        return date.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: '2-digit'});
    }
}
