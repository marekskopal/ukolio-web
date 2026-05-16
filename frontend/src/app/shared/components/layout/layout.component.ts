import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {User} from '@app/models/user';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';

@Component({
    selector: 'tm-layout',
    standalone: true,
    imports: [RouterOutlet, RouterLink, RouterLinkActive],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './layout.component.html',
    styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit {
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);

    protected readonly user = signal<User | null>(null);

    public async ngOnInit(): Promise<void> {
        try {
            this.user.set(await this.currentUserService.load());
        } catch {
            // Interceptor handles 401 -> refresh / logout
        }
    }

    protected logout(): void {
        this.currentUserService.clear();
        this.auth.logout();
    }
}
