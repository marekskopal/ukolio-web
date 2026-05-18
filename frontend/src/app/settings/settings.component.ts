import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators} from '@angular/forms';
import {AlertService} from '@app/services/alert.service';
import {AuthenticationService} from '@app/services/authentication.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-settings',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './settings.component.html',
    styleUrl: './settings.component.scss',
})
export class SettingsComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly translate = inject(TranslateService);
    private readonly alertService = inject(AlertService);

    protected readonly saving = signal(false);
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
}

function matchPasswords(control: AbstractControl): ValidationErrors | null {
    const password = control.get('newPassword')?.value;
    const confirm = control.get('confirmPassword')?.value;
    if (password !== '' && confirm !== '' && password !== confirm) {
        return {passwordMismatch: true};
    }
    return null;
}
