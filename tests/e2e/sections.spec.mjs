// Pages built from sections (session 45, D-45-01): the demo page "Portada amb seccions" as a
// guest at every width and colour mode, and the section editor as the administrator (list,
// gallery, a section form; then add a section, reorder with the keyboard, save and preview).
import { test, expect, checkPage, backend } from './support.mjs';

const DEMO_PATH = '/portada-seccions';

/** Scrolls through the page so lazy images load before checks and screenshots. */
async function loadLazyImages(page) {
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 400) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 30));
        }
        window.scrollTo(0, 0);
    });
    await page.waitForLoadState('networkidle');
}

/** The editor URL of the demo page (through the CRUD's "Sections" row operation). */
async function editorUrl(page) {
    await page.goto(backend('pages'));
    const href = await page.locator('tr', { hasText: 'Portada amb seccions' }).locator('a[href*="/sections/"]').first().getAttribute('href');
    test.skip(!href, 'No demo page built from sections (php spark modules:demo Pages).');
    await page.goto(href);
    await expect(page).toHaveURL(/\/pages\/\d+\/sections$/);

    return new URL(page.url()).pathname;
}

test.describe('public', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('the demo page built from sections', async ({ page }) => {
        const response = await page.request.get(DEMO_PATH);
        test.skip(response.status() === 404, 'No demo page built from sections.');

        await checkPage(page, DEMO_PATH, 'public-sections', { ready: loadLazyImages });

        const sections = page.locator('main section.kp-section');
        expect(await sections.count()).toBeGreaterThanOrEqual(8);
        await expect(page.locator('main h1')).toHaveCount(1);
        await expect(page.locator('main .kp-hero-title')).toBeVisible();
        expect(await page.locator('main img:not([alt])').count(), 'every image has an alt attribute').toBe(0);
        // Full width: the hero's background reaches both edges of the window.
        const box = await sections.first().boundingBox();
        expect(box.x).toBeLessThanOrEqual(0);
        expect(Math.round(box.width)).toBe(page.viewportSize().width);
    });
});

test.describe('section editor', () => {
    test('list, gallery and a section form', async ({ page }) => {
        const url = await editorUrl(page);

        await checkPage(page, url, 'sections-list');
        await checkPage(page, url + '/add', 'sections-gallery');
        await checkPage(page, url + '/new/pages.cards', 'sections-form');
    });

    test('add a section, reorder it with the keyboard, save and preview', async ({ page }) => {
        test.skip(test.info().project.name !== 'desktop-light', 'Once, at desktop width.');
        const url = await editorUrl(page);
        const rows = page.locator('[data-section-item]');
        const before = await rows.count();

        // Add a call to action at the end.
        await page.goto(url + '/add');
        await page.locator('a[href$="/new/pages.cta"]').click();
        await page.fill('input[name="f[title][ca]"]', 'Secció de prova e2e');
        await expect(page.locator('.kp-section-form.kp-locale-tabbed')).toHaveCount(1); // core/sections/form mounted
        await page.locator('[data-locale-tab="es"]').click();
        await expect(page.locator('input[name="f[title][es]"]')).toBeVisible();
        await expect(page.locator('input[name="f[title][ca]"]')).toBeHidden();
        await page.fill('input[name="f[title][es]"]', 'Sección de prueba e2e');
        await Promise.all([page.waitForURL(/\/sections#section-\d+$/), page.locator('.kp-section-editor button[type=submit]').click()]);
        await expect(rows).toHaveCount(before + 1);
        const added = rows.last();
        await expect(added).toContainText('Secció de prova e2e');
        const id = await added.getAttribute('data-id');

        // Move it up with the keyboard (the handle and the arrow keys), then save the order.
        const handle = added.locator('[data-section-handle]');
        await expect(handle).toBeVisible(); // shown once pages/section-list is mounted
        await handle.focus();
        await page.keyboard.press('ArrowUp');
        await expect(rows.nth(before - 1)).toHaveAttribute('data-id', id);
        await expect(page.locator('[data-section-status]')).toContainText('Crida a l’acció');
        await Promise.all([page.waitForURL(/\/sections/), page.locator('[data-order-save] button[type=submit]').click()]);
        await expect(rows.nth(before - 1)).toHaveAttribute('data-id', id);

        // Preview: the new section is on the page.
        const previewHref = await page.locator('a[href*="/preview/"]').first().getAttribute('href');
        const preview = await page.request.get(previewHref);
        expect(await preview.text()).toContain('Secció de prova e2e');

        // Clean up: delete it (confirmed in the Core's dialog).
        await page.locator(`#section-${id} button[formaction$="/delete"]`).click();
        await page.locator('.modal.show button.btn-danger, .modal.show [data-confirm-ok]').first().click();
        await page.waitForURL(/\/sections$/);
        await expect(rows).toHaveCount(before);
    });
});
