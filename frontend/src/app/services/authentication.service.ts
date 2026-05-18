import {HttpClient} from '@angular/common/http';
import {computed, inject, Injectable, signal} from '@angular/core';
import {Router} from '@angular/router';
import {Authentication} from '@app/models/authentication';
import {StorageService} from '@app/services/storage.service';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

const STORAGE_KEY_AUTH = 'authentication';

@Injectable({providedIn: 'root'})
export class AuthenticationService {
    private readonly http = inject(HttpClient);
    private readonly router = inject(Router);
    private readonly storage = inject(StorageService);

    public readonly authentication = signal<Authentication | null>(this.storage.get<Authentication>(STORAGE_KEY_AUTH));
    public readonly isLoggedIn = computed<boolean>(() => this.authentication() !== null);

    public async login(email: string, password: string): Promise<Authentication> {
        const auth = await firstValueFrom(
            this.http.post<Authentication>(`${environment.apiUrl}/authentication/login`, {email, password}),
        );
        this.setAuthentication(auth);
        return auth;
    }

    public async signUp(email: string, password: string, name: string, locale?: string): Promise<Authentication> {
        const auth = await firstValueFrom(
            this.http.post<Authentication>(`${environment.apiUrl}/authentication/sign-up`, {email, password, name, locale}),
        );
        this.setAuthentication(auth);
        return auth;
    }

    public async refreshToken(): Promise<Authentication> {
        const current = this.authentication();
        if (current === null) {
            throw new Error('Not authenticated');
        }
        const auth = await firstValueFrom(
            this.http.post<Authentication>(`${environment.apiUrl}/authentication/refresh-token`, {
                refreshToken: current.refreshToken,
            }),
        );
        this.setAuthentication(auth);
        return auth;
    }

    public async requestPasswordReset(email: string): Promise<void> {
        await firstValueFrom(
            this.http.post<void>(`${environment.apiUrl}/authentication/request-password-reset`, {email}),
        );
    }

    public async confirmPasswordReset(token: string, password: string): Promise<Authentication> {
        const auth = await firstValueFrom(
            this.http.post<Authentication>(`${environment.apiUrl}/authentication/confirm-password-reset`, {token, password}),
        );
        this.setAuthentication(auth);
        return auth;
    }

    public async changePassword(currentPassword: string, newPassword: string): Promise<void> {
        await firstValueFrom(
            this.http.post<void>(`${environment.apiUrl}/current-user/password`, {currentPassword, newPassword}),
        );
    }

    public logout(): void {
        this.storage.remove(STORAGE_KEY_AUTH);
        this.authentication.set(null);
        this.router.navigate(['/login']);
    }

    private setAuthentication(auth: Authentication): void {
        this.storage.set(STORAGE_KEY_AUTH, auth);
        this.authentication.set(auth);
    }
}
