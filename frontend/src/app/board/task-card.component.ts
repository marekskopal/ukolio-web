import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {Task} from '@app/models/task';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-task-card',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-card.component.html',
    styleUrl: './task-card.component.scss',
})
export class TaskCardComponent {
    public readonly task = input.required<Task>();

    protected stripMd(s: string): string {
        return s.replace(/[#*_`~>\-]/g, '').replace(/\s+/g, ' ').trim();
    }

    protected isOverdue(dueDate: string): boolean {
        return new Date(dueDate) < new Date(new Date().toDateString());
    }

    protected formatDate(dueDate: string): string {
        return new Date(dueDate).toLocaleDateString();
    }
}
