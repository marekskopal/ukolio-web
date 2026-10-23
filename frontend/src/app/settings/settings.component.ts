import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators} from '@angular/forms';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {AvatarComponent} from '@app/shared/components/avatar/avatar.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-settings',
    standalone: true,
    imports: [AvatarComponent, ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './settings.component.html',
    styleUrl: './settings.component.scss',
})
export class SettingsComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly translate = inject(TranslateService);
    private readonly alertService = inject(AlertService);

    protected readonly user = this.currentUserService.currentUser;
    protected readonly avatarBusy = signal(false);
    protected readonly avatarError = signal<string | null>(null);
    protected readonly saving = signal(false);
    protected readonly exporting = signal(false);
    protected readonly deleting = signal(false);
    protected readonly form = this.fb.nonNullable.group(
        {
            currentPassword: ['', Validators.required],
            newPassword: ['', [Validators.required, Validators.minLength(8)]],
            confirmPassword: ['', Validators.required],
        },
        {validators: [matchPasswords]},
    );
    protected readonly hasMismatch = computed<boolean>(() => {
        return this.form.errors?.['passwordMismatch'] === true && this.form.controls.confirmPassword.dirty;
    });

    protected async onAvatarSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        if (!file) {
            return;
        }
        // Mirrors the backend's checks so the common mistakes fail fast, without an upload.
        if (!AVATAR_TYPES.includes(file.type)) {
            this.avatarError.set('app.settings.avatar.errorType');
            return;
        }
        if (file.size > AVATAR_MAX_BYTES) {
            this.avatarError.set('app.settings.avatar.errorSize');
            return;
        }
        this.avatarError.set(null);
        this.avatarBusy.set(true);
        try {
            await this.currentUserService.uploadAvatar(file);
        } catch {
            // error interceptor
        } finally {
            this.avatarBusy.set(false);
        }
    }

    protected async onAvatarRemove(): Promise<void> {
        this.avatarError.set(null);
        this.avatarBusy.set(true);
        try {
            await this.currentUserService.removeAvatar();
        } catch {
            // error interceptor
        } finally {
            this.avatarBusy.set(false);
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.changePassword(this.form.value.currentPassword!, this.form.value.newPassword!);
            const message = await this.translate.instant('app.settings.changePassword.success') as string;
            this.alertService.success(message);
            this.form.reset();
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onExport(): Promise<void> {
        this.exporting.set(true);
        try {
            const blob = await this.currentUserService.exportData();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ukolio-export-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch {
            // error interceptor
        } finally {
            this.exporting.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const message = this.translate.instant('app.settings.dangerZone.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        this.deleting.set(true);
        try {
            await this.currentUserService.deleteAccount();
            this.currentUserService.clear();
            this.auth.logout();
        } catch {
            // error interceptor surfaces the message (incl. blocking workspaces)
        } finally {
            this.deleting.set(false);
        }
    }
}

const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const AVATAR_MAX_BYTES = 1024 * 1024;

function matchPasswords(control: AbstractControl): ValidationErrors | null {
    const password = control.get('newPassword')?.value;
    const confirm = control.get('confirmPassword')?.value;
    if (password !== '' && confirm !== '' && password !== confirm) {
        return {passwordMismatch: true};
    }
    return null;
}
