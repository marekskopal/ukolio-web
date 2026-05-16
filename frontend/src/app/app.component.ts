import {ChangeDetectionStrategy, Component} from '@angular/core';
import {RouterOutlet} from '@angular/router';
import {AlertComponent} from '@app/shared/components/alert/alert.component';

@Component({
    selector: 'tm-app',
    standalone: true,
    imports: [RouterOutlet, AlertComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './app.component.html',
})
export class AppComponent {}
