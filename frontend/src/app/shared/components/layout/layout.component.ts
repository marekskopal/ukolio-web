import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {Locale, User} from '@app/models/user';
import {Workspace} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LanguageService} from '@app/services/language.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-layout',
    standalone: true,
    imports: [RouterOutlet, RouterLink, RouterLinkActive, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './layout.component.html',
    styleUrl: './layout.component.scss',
})
export class LayoutComponent implements OnInit {
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly languageService = inject(LanguageService);
    private readonly translate = inject(TranslateService);
    private readonly alertService = inject(AlertService);
    private readonly router = inject(Router);

    protected readonly user = signal<User | null>(null);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly currentWorkspaceId = this.workspaceService.currentWorkspaceId;
    protected readonly currentWorkspace = computed<Workspace | null>(() => {
        const id = this.currentWorkspaceId();
        return this.workspaces().find((w) => w.id === id) ?? null;
    });
    protected readonly switcherOpen = signal(false);
    protected readonly langMenuOpen = signal(false);
    protected readonly currentLang = this.languageService.currentLang;
    protected readonly supportedLangs = this.languageService.supportedLangs;

    public async ngOnInit(): Promise<void> {
        try {
            const user = await this.currentUserService.load();
            this.user.set(user);
            this.workspaceService.currentWorkspaceId.set(user.currentWorkspaceId);
            await this.workspaceService.loadAll();
        } catch {
            // Interceptor handles 401 -> refresh / logout
        }
    }

    protected toggleSwitcher(): void {
        this.switcherOpen.update((v) => !v);
    }

    protected toggleLangMenu(): void {
        this.langMenuOpen.update((v) => !v);
    }

    protected async setLanguage(lang: Locale): Promise<void> {
        this.langMenuOpen.set(false);
        this.languageService.use(lang, {persist: true, sync: true});
    }

    protected async switchTo(workspaceId: number): Promise<void> {
        this.switcherOpen.set(false);
        try {
            await this.workspaceService.switchTo(workspaceId);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected async createWorkspace(): Promise<void> {
        this.switcherOpen.set(false);
        const promptText = await this.translate.instant('app.workspaces.createPrompt') as string;
        const name = prompt(promptText);
        if (name === null || name.trim() === '') {
            return;
        }
        try {
            const ws = await this.workspaceService.create(name.trim());
            await this.workspaceService.switchTo(ws.id);
            const message = await this.translate.instant('app.workspaces.created', {name: ws.name}) as string;
            this.alertService.success(message);
            await this.router.navigate(['/projects']);
        } catch {
            // error interceptor
        }
    }

    protected logout(): void {
        this.currentUserService.clear();
        this.workspaceService.clear();
        this.auth.logout();
    }
}
