import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {User} from '@app/models/user';
import {Invitation, Workspace, WorkspaceMember} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-workspaces',
    standalone: true,
    imports: [FormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './workspaces.component.html',
    styleUrl: './workspaces.component.scss',
})
export class WorkspacesComponent implements OnInit {
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly workspaces = this.workspaceService.workspaces;
    protected readonly user = signal<User | null>(null);
    protected readonly selected = signal<Workspace | null>(null);
    protected readonly members = signal<WorkspaceMember[]>([]);
    protected readonly invitations = signal<Invitation[]>([]);
    protected readonly inviteEmail = signal('');
    protected readonly isOwner = computed<boolean>(() => {
        const ws = this.selected();
        const u = this.user();
        return ws !== null && u !== null && ws.ownerId === u.id;
    });

    public async ngOnInit(): Promise<void> {
        this.loading.set(true);
        try {
            const [user] = await Promise.all([this.currentUserService.load(), this.workspaceService.loadAll()]);
            this.user.set(user);
            const current = this.workspaces().find((w) => w.id === user.currentWorkspaceId) ?? this.workspaces()[0] ?? null;
            if (current !== null) {
                await this.select(current);
            }
        } finally {
            this.loading.set(false);
        }
    }

    protected async select(ws: Workspace): Promise<void> {
        this.selected.set(ws);
        const [members, invitations] = await Promise.all([
            this.workspaceService.getMembers(ws.id),
            this.workspaceService.getInvitations(ws.id).catch(() => []),
        ]);
        this.members.set(members);
        this.invitations.set(invitations);
    }

    protected async rename(): Promise<void> {
        const ws = this.selected();
        if (ws === null) {
            return;
        }
        const promptText = await this.translate.instant('app.workspaces.renamePrompt') as string;
        const name = prompt(promptText, ws.name);
        if (name === null || name.trim() === '' || name.trim() === ws.name) {
            return;
        }
        try {
            const updated = await this.workspaceService.update(ws.id, name.trim());
            this.selected.set(updated);
            this.alertService.success(await this.translate.instant('app.workspaces.renamed') as string);
        } catch {
            // error interceptor
        }
    }

    protected async invite(): Promise<void> {
        const ws = this.selected();
        const email = this.inviteEmail().trim();
        if (ws === null || email === '') {
            return;
        }
        try {
            const invitation = await this.workspaceService.createInvitation(ws.id, email);
            this.invitations.update((all) => [invitation, ...all]);
            this.inviteEmail.set('');
            this.alertService.success(await this.translate.instant('app.workspaces.invitationSent', {email}) as string);
        } catch {
            // error interceptor
        }
    }

    protected async cancelInvitation(invitation: Invitation): Promise<void> {
        const confirmMessage = await this.translate.instant('app.workspaces.cancelInvitationConfirm', {email: invitation.email}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.deleteInvitation(invitation.id);
            this.invitations.update((all) => all.filter((i) => i.id !== invitation.id));
        } catch {
            // error interceptor
        }
    }

    protected async removeMember(member: WorkspaceMember): Promise<void> {
        const ws = this.selected();
        if (ws === null) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.removeMemberConfirm', {
            name: member.name,
            workspace: ws.name,
        }) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.removeMember(ws.id, member.userId);
            this.members.update((all) => all.filter((m) => m.userId !== member.userId));
            this.alertService.success(await this.translate.instant('app.workspaces.memberRemoved') as string);
        } catch {
            // error interceptor
        }
    }

    protected async deleteWorkspace(): Promise<void> {
        const ws = this.selected();
        if (ws === null) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.workspaces.deleteConfirm', {name: ws.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.workspaceService.delete(ws.id);
            this.alertService.success(await this.translate.instant('app.workspaces.deleted') as string);
            this.selected.set(null);
            const next = this.workspaces()[0];
            if (next !== undefined) {
                await this.select(next);
            }
        } catch {
            // error interceptor
        }
    }

    protected updateInviteEmail(value: string): void {
        this.inviteEmail.set(value);
    }
}
