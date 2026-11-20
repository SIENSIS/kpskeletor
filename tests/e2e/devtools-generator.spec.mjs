// The Devtools code generator (session 42): the wizard with a definition loaded and its preview,
// at every width and colour mode, without overflow, CSP or axe (WCAG 2.1 AA) violations. Devtools
// exists only on a development site with the module enabled (php spark modules:enable Devtools)
// and answers only http://localhost: elsewhere these tests are skipped. Nothing is written (the
// write needs a re-authentication the e2e session never does).
import { readFileSync } from 'node:fs';
import { test, expect, checkPage, backend } from './support.mjs';

const definition = readFileSync('modules/Core/Tests/Fixtures/Generator/library.json', 'utf8').replaceAll('Library', 'E2elibrary').replaceAll('library_', 'e2elibrary_');

test.describe('code generator', () => {
    test.beforeEach(async ({ page }) => {
        const response = await page.goto(backend('devtools/generator'));
        test.skip(response === null || response.status() === 404 || response.status() === 403, 'Devtools is not available on this site.');

        await page.fill('#definition-json', definition);
        await Promise.all([page.waitForLoadState('networkidle'), page.locator('form[action$="generator/load"] button[type=submit]').click()]);
    });

    test('the wizard with a definition', async ({ page }) => {
        await checkPage(page, backend('devtools/generator'), 'devtools-generator');
        await expect(page.locator('[data-generator-table]')).toHaveCount(2);
        await expect(page.locator('[data-generator-permission]').first()).toContainText('e2elibrary-shelves.list');
    });

    test('the preview of every file', async ({ page }) => {
        await checkPage(page, backend('devtools/generator/preview'), 'devtools-generator-preview');
        await expect(page.locator('[data-generator-file]').first()).toBeVisible();
        await expect(page.locator('form[data-generator-write] input[name=fingerprint]')).toHaveValue(/^[0-9a-f]{64}$/);
    });
});
