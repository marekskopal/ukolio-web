import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {Tag} from '@app/models/tag';
import {Task} from '@app/models/task';
import {WorkspaceMember} from '@app/models/workspace';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {AvatarComponent} from '@app/shared/components/avatar/avatar.component';
import {TranslatePipe} from '@ngx-translate/core';

const MAX_VISIBLE_TAGS = 3;

@Component({
    selector: 'uk-task-card',
    standalone: true,
    imports: [AvatarComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-card.component.html',
    styleUrl: './task-card.component.scss',
})
export class TaskCardComponent {
    public readonly task = input.required<Task>();
    public readonly workspaceTags = input<Tag[]>([]);
    public readonly members = input<WorkspaceMember[]>([]);

    protected readonly assignee = computed<WorkspaceMember | null>(() => {
        const id = this.task().assigneeId;
        if (id === null) return null;
        return this.members().find((m) => m.userId === id) ?? null;
    });

    protected readonly visibleTags = computed<Tag[]>(() => this.taskTags().slice(0, MAX_VISIBLE_TAGS));

    protected readonly hiddenTagCount = computed<number>(() => Math.max(0, this.taskTags().length - MAX_VISIBLE_TAGS));

    private readonly taskTags = computed<Tag[]>(() => {
        const ids = new Set(this.task().tagIds ?? []);
        if (ids.size === 0) {
            return [];
        }
        const byId = new Map(this.workspaceTags().map((t) => [t.id, t]));
        const result: Tag[] = [];
        for (const id of ids) {
            const tag = byId.get(id);
            if (tag) result.push(tag);
        }
        return result;
    });

    protected stripMd(s: string): string {
        return s.replace(/[#*_`~>-]/g, '').replace(/\s+/g, ' ').trim();
    }

    protected isOverdue(dueDate: string): boolean {
        return new Date(dueDate) < new Date(new Date().toDateString());
    }

    protected formatDate(dueDate: string): string {
        return new Date(dueDate).toLocaleDateString();
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected priorityForeground(color: string): string {
        return pickReadableForeground(color);
    }
}
