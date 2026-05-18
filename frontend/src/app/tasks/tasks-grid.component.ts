import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, HostListener, inject, OnInit, signal, viewChild} from '@angular/core';
import {takeUntilDestroyed, toSignal} from '@angular/core/rxjs-interop';
import {FormControl, ReactiveFormsModule} from '@angular/forms';
import {TaskDetailDrawerComponent} from '@app/board/task-detail-drawer.component';
import {ProjectField} from '@app/models/field';
import {Status} from '@app/models/status';
import {OrderDirection, Task, TaskListItem, TaskOrderBy} from '@app/models/task';
import {WorkflowWithStatuses} from '@app/models/workflow';
import {BoardService} from '@app/services/board.service';
import {FieldService} from '@app/services/field.service';
import {TaskService} from '@app/services/task.service';
import {WorkflowService} from '@app/services/workflow.service';
import {PaginationComponent} from '@app/shared/components/pagination/pagination.component';
import {TranslatePipe} from '@ngx-translate/core';
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

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly search = toSignal(
        this.searchControl.valueChanges.pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed()),
        {initialValue: ''},
    );

    protected readonly selectedStatusIds = signal<number[]>([]);
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

    private readonly statusDetails = viewChild<ElementRef<HTMLDetailsElement>>('statusDetails');

    protected readonly offset = computed<number>(() => (this.page() - 1) * this.pageSize());

    private readonly queryParams = computed<QueryParams>(() => ({
        limit: this.pageSize(),
        offset: this.offset(),
        orderBy: this.sortBy(),
        orderDirection: this.sortDirection(),
        search: this.search() === '' ? undefined : this.search(),
        statusIds: this.selectedStatusIds().length > 0 ? this.selectedStatusIds() : undefined,
        onlyActive: this.onlyActive() ? true : undefined,
    }));

    public ngOnInit(): void {
        void this.loadWorkflows();
    }

    public constructor() {
        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe(() => this.page.set(1));

        effect(() => {
            const params = this.queryParams();
            void this.fetchTasks(params);
        });
    }

    private async loadWorkflows(): Promise<void> {
        try {
            this.workflows.set(await this.workflowService.getWorkflows());
        } catch {
            this.workflows.set([]);
        }
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
                onlyActive: params.onlyActive,
            });
            this.tasks.set(result.tasks);
            this.count.set(result.count);
        } catch {
            this.tasks.set([]);
            this.count.set(0);
        } finally {
            this.loading.set(false);
        }
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        const details = this.statusDetails()?.nativeElement;
        if (!details?.open || !(target instanceof Node)) {
            return;
        }
        if (!details.contains(target)) {
            details.open = false;
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

    protected onOnlyActiveToggle(event: Event): void {
        this.onlyActive.set((event.target as HTMLInputElement).checked);
        this.page.set(1);
    }

    protected clearFilters(): void {
        this.searchControl.setValue('');
        this.selectedStatusIds.set([]);
        this.onlyActive.set(false);
        this.page.set(1);
    }

    protected onPageChange(page: number): void {
        this.page.set(page);
    }

    protected onPageSizeChange(size: number): void {
        this.pageSize.set(size);
        this.page.set(1);
    }

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
