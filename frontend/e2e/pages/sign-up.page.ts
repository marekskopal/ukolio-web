import {expect, Page} from '@playwright/test';

export class SignUpPage {
    public constructor(private readonly page: Page) {}

    public async goto(): Promise<void> {
        await this.page.goto('sign-up');
    }

    public async signUp(name: string, email: string, password: string): Promise<void> {
        await this.page.fill('#signup-name', name);
        await this.page.fill('#signup-email', email);
        await this.page.fill('#signup-password', password);
        await this.page.getByRole('button', {name: 'Sign up'}).click();
    }

    public async expectLandedInsideApp(): Promise<void> {
        // Successful sign-up lands the user inside the authenticated layout, defaulting to /projects.
        await expect(this.page).toHaveURL(/\/projects/, {timeout: 15_000});
    }
}
