// End-to-end tests (docs/development.md, "End-to-end and accessibility tests"): the main screens at phone,
// tablet and desktop widths, in light and dark mode; every page fails on horizontal overflow,
// on a Content-Security-Policy violation and on an axe (WCAG 2.1 AA) violation.
//
//   php spark app:install --env=development --url=http://localhost:8089/ --db=sqlite --demo --2fa=none …   (once, see the docs)
//   E2E_USER=admin@example.com E2E_PASSWORD=… npm run e2e
//
// E2E_BASE_URL: an already running site (default: `php spark serve` started here on port 8089).
import { defineConfig } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8089);
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${port}`;

const widths = {
    phone: { width: 390, height: 844 },
    tablet: { width: 820, height: 1180 },
    desktop: { width: 1440, height: 900 },
};

const projects = [];
for (const [device, viewport] of Object.entries(widths)) {
    for (const scheme of ['light', 'dark']) {
        projects.push({
            name: `${device}-${scheme}`,
            use: { viewport, colorScheme: scheme, hasTouch: device === 'phone' },
        });
    }
}

export default defineConfig({
    testDir: 'tests/e2e',
    testMatch: '**/*.spec.mjs',
    outputDir: 'writable/e2e/results',
    globalSetup: './tests/e2e/global-setup.mjs',
    fullyParallel: false,
    workers: 1, // one PHP development server: keep requests sequential
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never', outputFolder: 'writable/e2e/report' }]] : 'list',
    use: {
        baseURL,
        locale: 'ca-ES',
        deviceScaleFactor: 1,
        storageState: 'writable/e2e/admin.json',
        trace: 'off',
    },
    projects,
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
            command: `php spark serve --port ${port}`,
            url: `${baseURL}/login`,
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
        },
});
