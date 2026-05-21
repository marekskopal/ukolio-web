import {expect, test} from '@playwright/test';

import {LayoutPage} from './pages/layout.page';
import {SignUpPage} from './pages/sign-up.page';

test.use({storageState: {cookies: [], origins: []}});

test.describe('Sign-up', () => {
    test('a brand-new user signs up and lands inside the app with a workspace ready', async ({page}) => {
        const signUp = new SignUpPage(page);
        const layout = new LayoutPage(page);

        const stamp = Date.now();
        const email = `signup-${stamp}@ukolio.test`;
        const name = `Signup ${stamp}`;

        await signUp.goto();
        await signUp.signUp(name, email, 'Test1234!');
        await signUp.expectLandedInsideApp();

        await layout.expectVisible();
        const workspaceName = await layout.currentWorkspaceName();
        expect(workspaceName.length).toBeGreaterThan(0);
    });

    test('the sign-up form blocks submission with an invalid email', async ({page}) => {
        const signUp = new SignUpPage(page);
        await signUp.goto();
        await page.fill('#signup-name', 'Bad Email');
        await page.fill('#signup-email', 'not-an-email');
        await page.fill('#signup-password', 'Test1234!');
        await expect(page.getByRole('button', {name: 'Sign up'})).toBeDisabled();
    });
});
