import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup, moveItemInArray, transferArrayItem} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {TaskCardComponent} from '@app/board/task-card.component';
import {TaskDetailDrawerComponent} from '@app/board/task-detail-drawer.component';
import {Board} from '@app/models/board';
import {ProjectField} from '@app/models/field';
import {Status} from '@app/models/status';
import {Task} from '@app/models/task';
import {BoardService} from '@app/services/board.service';
import {FieldService} from '@app/services/field.service';
import {TaskService} from '@app/services/task.service';
import {TranslatePipe} from '@ngx-translate/core';

interface Column {
    status: Status;
    tasks: Task[];
}

@Component({
    selector: 'uk-board',
    standalone: true,
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, RouterLink, TaskCardComponent, TaskDetailDrawerComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './board.component.html',
    styleUrl: './board.component.scss',
})
export class BoardComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly boardService = inject(BoardService);
    private readonly taskService = inject(TaskService);
    private readonly fieldService = inject(FieldService);

    protected readonly loading = signal(true);
    protected readonly board = signal<Board | null>(null);
    protected readonly projectId = signal<number | null>(null);
    protected readonly projectFields = signal<ProjectField[]>([]);

    protected readonly drawerOpen = signal(false);
    protected readonly editingTask = signal<Task | null>(null);
    protected readonly defaultStatusId = signal<number | null>(null);

    protected readonly columns = computed<Column[]>(() => {
        const board = this.board();
        if (!board) {
            return [];
        }
        return [...board.statuses]
            .sort((a, b) => a.position - b.position)
            .map((status) => ({
                status,
                tasks: board.tasks
                    .filter((t) => t.statusId === status.id)
                    .sort((a, b) => a.position - b.position),
            }));
    });

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.projectId.set(id);
        await Promise.all([this.loadBoard(), this.loadProjectFields()]);
    }

    private async loadBoard(): Promise<void> {
        this.loading.set(true);
        try {
            this.board.set(await this.boardService.getBoard(this.projectId()!));
        } finally {
            this.loading.set(false);
        }
    }

    private async loadProjectFields(): Promise<void> {
        const id = this.projectId();
        if (id === null) return;
        try {
            this.projectFields.set(await this.fieldService.listProjectFields(id));
        } catch {
            this.projectFields.set([]);
        }
    }

    protected async onDrop(event: CdkDragDrop<Task[]>, targetStatus: Status): Promise<void> {
        const previousArr = event.previousContainer.data;
        const currentArr = event.container.data;
        let movedTask: Task;

        if (event.previousContainer === event.container) {
            if (event.previousIndex === event.currentIndex) {
                return;
            }
            movedTask = currentArr[event.previousIndex];
            moveItemInArray(currentArr, event.previousIndex, event.currentIndex);
        } else {
            movedTask = previousArr[event.previousIndex];
            transferArrayItem(previousArr, currentArr, event.previousIndex, event.currentIndex);
        }

        currentArr.forEach((t, i) => { t.position = i; t.statusId = targetStatus.id; });
        previousArr.forEach((t, i) => { t.position = i; });

        this.board.update((b) => b ? {...b, tasks: [...b.tasks]} : b);

        try {
            await this.taskService.moveTask(movedTask.id, targetStatus.id, event.currentIndex);
        } catch {
            await this.loadBoard();
        }
    }

    protected openCreate(status: Status): void {
        this.editingTask.set(null);
        this.defaultStatusId.set(status.id);
        this.drawerOpen.set(true);
    }

    protected openEdit(task: Task): void {
        this.editingTask.set(task);
        this.defaultStatusId.set(null);
        this.drawerOpen.set(true);
    }

    protected closeDrawer(): void {
        this.drawerOpen.set(false);
        this.editingTask.set(null);
    }

    protected onTaskSaved(_task: Task): void {
        this.closeDrawer();
        void this.loadBoard();
    }

    protected onTaskDeleted(_id: number): void {
        this.closeDrawer();
        void this.loadBoard();
    }
}
