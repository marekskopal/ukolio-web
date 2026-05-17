import {ChangeDetectionStrategy, Component} from '@angular/core';
import {TranslatePipe} from '@ngx-translate/core';

@Component({
    selector: 'uk-agents',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    styleUrl: './agents.component.scss',
    template: `
        <header class="agents-header">
            <div>
                <h1 class="uk-h2">{{ 'app.agents.title' | translate }}</h1>
                <p class="uk-caption">{{ 'app.agents.subtitle' | translate }}</p>
            </div>
        </header>

        <div class="agents-kpis">
            @for (kpi of kpis; track kpi.label) {
                <div class="uk-card kpi">
                    <div class="uk-overline kpi-label">{{ kpi.label | translate }}</div>
                    <div class="kpi-value">—</div>
                    <div class="kpi-sub">{{ 'app.agents.placeholder' | translate }}</div>
                </div>
            }
        </div>

        <div class="agents-filters">
            <span class="uk-caption">{{ 'app.agents.show' | translate }}</span>
            @for (chip of chips; track chip; let i = $index) {
                <button
                    type="button"
                    class="agents-chip"
                    [class.agents-chip--active]="i === 0"
                >{{ chip | translate }}</button>
            }
        </div>

        <div class="uk-card agents-empty">
            <p class="uk-body-sm">{{ 'app.agents.empty' | translate }}</p>
        </div>
    `,
})
export class AgentsComponent {
    protected readonly kpis: {label: string}[] = [
        {label: 'app.agents.kpi.events'},
        {label: 'app.agents.kpi.activeAgents'},
        {label: 'app.agents.kpi.tasksCreated'},
        {label: 'app.agents.kpi.tasksClosed'},
    ];

    protected readonly chips: string[] = [
        'app.agents.chips.all',
        'app.agents.chips.humans',
        'app.agents.chips.agents',
        'app.agents.chips.comments',
        'app.agents.chips.statusChanges',
    ];
}
