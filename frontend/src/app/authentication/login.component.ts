import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AuthenticationService} from '@app/services/authentication.service';

@Component({
    selector: 'tm-login',
    standalone: true,
    imports: [ReactiveFormsModule, RouterLink],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './login.component.html',
    styleUrl: './auth-page.scss',
})
export class LoginComponent {
    private readonly fb = inject(FormBuilder);
    private readonly auth = inject(AuthenticationService);
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);

    protected readonly saving = signal(false);
    protected readonly form = this.fb.nonNullable.group({
        email: ['', [Validators.required, Validators.email]],
        password: ['', Validators.required],
    });

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        try {
            await this.auth.login(this.form.value.email!, this.form.value.password!);
            const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl') ?? '/projects';
            this.router.navigateByUrl(returnUrl);
        } catch {
            // error interceptor shows the toast
        } finally {
            this.saving.set(false);
        }
    }
}
