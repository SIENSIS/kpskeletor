// Reusable blocks, theme zones, footer columns and page models (session 47, D-47-01, D-47-02):
// a public page with the demo "partner logos" block before the footer and the footer in columns,
// at every width and colour mode; the blocks screens and the gallery of page models as the
// administrator; and (desktop light only, to create each page once) a page made from each model,
// previewed and checked like any page.
import { test, expect, checkPage, backend } from './support.mjs';

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

test.describe('public', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('a page with a zone and the footer in columns', async ({ page }) => {
        const response = await page.request.get('/qui-som');
        test.skip(response.status() === 404, 'No Pages demo data.');

        await checkPage(page, '/qui-som', 'public-zone-footer', { ready: loadLazyImages });

        const zone = page.locator('main .site-zone-before-footer [data-block]');
        test.skip(await zone.count() === 0, 'No demo block placed in public.before_footer.');
        await expect(zone.first().locator('section.kp-section-pages-logos, section[data-section-type="pages.logos"]').first()).toBeVisible();
        expect(await zone.locator('img:not([alt])').count(), 'every logo has an alt attribute').toBe(0);

        const columns = page.locator('footer .site-footer-columns > nav');
        expect(await columns.count()).toBeGreaterThanOrEqual(2);
        await expect(columns.first().locator('h2')).toBeVisible();
        await expect(page.locator('main h1')).toHaveCount(1);

        await page.locator('footer').scrollIntoViewIfNeeded();
        await page.screenshot({ path: `writable/e2e/screenshots/${test.info().project.name}/public-footer.png`, fullPage: false });
    });
});

test.describe('blocks and models', () => {
    test('the blocks screens and the models gallery', async ({ page }) => {
        await checkPage(page, backend('pages/blocks'), 'blocks-list');

        const href = await page.locator('a[href*="/pages/blocks/sections/"]').first().getAttribute('href');
        test.skip(!href, 'No demo block.');
        await page.goto(href);
        await expect(page).toHaveURL(/\/pages\/blocks\/\d+\/sections$/);
        const sections = new URL(page.url()).pathname;

        await checkPage(page, sections, 'block-sections');
        await checkPage(page, sections + '/add', 'block-gallery');
        await expect(page.locator('a[href$="/new/pages.block"]')).toHaveCount(0);
        await checkPage(page, sections.replace(/\/sections$/, '/zones'), 'block-zones');
        await checkPage(page, backend('pages/models'), 'page-models');
    });

    for (const [key, label] of [['pages.school', 'Centre educatiu'], ['pages.company', 'Empresa']]) {
        test(`a page from the model ${label}`, async ({ page }, testInfo) => {
            test.skip(testInfo.project.name !== 'desktop-light', 'Creates a page: once, on desktop light.');

            await page.goto(backend('pages/models'));
            await page.locator(`form:has(input[name="model"][value="${key}"]) button[type="submit"]`).click();
            await expect(page).toHaveURL(/\/pages\/\d+\/sections(#.*)?$/);
            expect(await page.locator('.kp-section-row').count()).toBeGreaterThanOrEqual(6);

            const id = new URL(page.url()).pathname.match(/\/pages\/(\d+)\/sections/)[1];
            // The new page's preview (a draft), through the editor's "Preview" button.
            await page.goto(backend(`pages/${id}/sections`));
            const url = await page.locator('a[target="_blank"][href*="/preview/"]').first().getAttribute('href');
            await checkPage(page, url, `model-${key.split('.')[1]}`, { ready: loadLazyImages });
            await expect(page.locator('main h1')).toHaveCount(1);
            expect(await page.locator('main img:not([alt])').count()).toBe(0);
        });
    }
});
