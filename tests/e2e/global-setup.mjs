// Logs the administrator in once and stores the session for every test (the login form is
// rate limited, so the tests never log in themselves).
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

export default async function globalSetup(config) {
    const { baseURL, storageState } = config.projects[0].use;
    const user = process.env.E2E_USER ?? 'admin@example.com';
    const password = process.env.E2E_PASSWORD;

    if (!password) {
        throw new Error('Set E2E_PASSWORD (and E2E_USER) to the administrator of the demo site (docs/development.md).');
    }

    mkdirSync('writable/e2e', { recursive: true });

    const browser = await chromium.launch();
    const page = await browser.newPage({ baseURL, locale: 'ca-ES' });

    await page.goto('/login');
    await page.fill('input[name=email]', user);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForURL(/\/backend/, { timeout: 30_000 }), page.click('button[type=submit]')]);
    await page.context().storageState({ path: storageState });
    await browser.close();
}
