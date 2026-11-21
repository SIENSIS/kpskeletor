// Section types of session 46 (D-46-01 … D-46-04) on the demo pages "Notícies", "Sortides",
// "Agenda" and "Més seccions", as a guest at every width and colour mode (axe, CSP, no horizontal
// scroll), plus the behaviours: the carousel does not rotate by default and works with the
// keyboard, the gallery's lightbox, and an embed that requests nothing from the other site until
// the visitor clicks.
import { test, expect, checkPage } from './support.mjs';

/** Scrolls through the page so lazy images load before checks. */
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

async function skipWithout(page, path) {
    const response = await page.request.get(path);
    test.skip(response.status() === 404, `No demo page ${path} (php spark modules:demo Pages).`);
}

test.use({ storageState: { cookies: [], origins: [] } });

for (const [path, name] of [['/noticies', 'public-news-page'], ['/sortides', 'public-outings-page'], ['/agenda', 'public-diary-page'], ['/mes-seccions', 'public-more-sections']]) {
    test(`demo page ${path}`, async ({ page }) => {
        await skipWithout(page, path);
        await checkPage(page, path, name, { ready: loadLazyImages });
        await expect(page.locator('main h1')).toHaveCount(1);
        expect(await page.locator('main img:not([alt])').count(), 'every image has an alt attribute').toBe(0);
    });
}

test('the news pages show their lists with pages', async ({ page }) => {
    await skipWithout(page, '/sortides');
    await page.goto('/sortides');
    await expect(page.locator('[data-section-type="news.list"] article').first()).toBeVisible();
    const next = page.locator('[data-section-type="news.list"] a[rel="next"]');

    if (await next.count()) {
        await Promise.all([page.waitForURL(/page-\d+=2/), next.click()]);
        await expect(page.locator('[data-section-type="news.list"] .page-item.active')).toContainText('2');
    }
});

test('the carousel does not rotate by default and works with the keyboard', async ({ page }) => {
    await skipWithout(page, '/mes-seccions');
    await page.goto('/mes-seccions');
    const carousel = page.locator('[data-component="pages/carousel"]');
    test.skip((await carousel.count()) === 0, 'No carousel on the demo page.');

    await expect(carousel).toHaveClass(/is-enhanced/);
    const slides = carousel.locator('[data-carousel-slide]');
    await expect(carousel).toHaveAttribute('data-rotate', '0');
    await expect(carousel.locator('[data-carousel-pause]')).toHaveCount(0);
    await expect(slides.nth(0)).toBeVisible();
    await expect(slides.nth(1)).toBeHidden();

    await page.waitForTimeout(1500);
    await expect(slides.nth(0)).toBeVisible(); // still the first one: no rotation

    await carousel.locator('[data-carousel-next]').click();
    await expect(slides.nth(1)).toBeVisible();
    await expect(carousel.locator('[data-carousel-dot="1"]')).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('ArrowRight');
    await expect(slides.nth(2)).toBeVisible();
    await page.keyboard.press('ArrowLeft');
    await expect(slides.nth(1)).toBeVisible();
    await carousel.locator('[data-carousel-dot="0"]').click();
    await expect(slides.nth(0)).toBeVisible();
});

test('the gallery opens its images in a lightbox', async ({ page }) => {
    await skipWithout(page, '/mes-seccions');
    await page.goto('/mes-seccions');
    const gallery = page.locator('[data-component="files/gallery"]');
    test.skip((await gallery.count()) === 0, 'No gallery on the demo page (php spark modules:demo Files).');

    await expect(gallery).toHaveAttribute('data-ready', '1'); // the lightbox is mounted
    const first = gallery.locator('[data-gallery-item]').first();
    await first.click();
    const dialog = gallery.locator('[data-lightbox]');
    await expect(dialog).toBeVisible();
    const caption = dialog.locator('[data-lightbox-caption]');
    const before = await caption.textContent();
    await page.keyboard.press('ArrowRight');
    await expect(caption).not.toHaveText(before);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(first).toBeFocused();
});

test('an embed requests nothing from the other site before the click', async ({ page }) => {
    await skipWithout(page, '/mes-seccions');
    const requests = [];
    await page.route(/youtube-nocookie\.com/, (route) => {
        requests.push(route.request().url());
        route.abort();
    });
    await page.goto('/mes-seccions');
    const embed = page.locator('[data-component="pages/embed"]');
    test.skip((await embed.count()) === 0, 'The demo embed is hidden until its host is allowed in Settings › Security.');

    await loadLazyImages(page);
    await expect(embed.locator('iframe')).toHaveCount(0);
    expect(requests, 'no request before the click').toEqual([]);

    await embed.locator('[data-embed-load]').click();
    const frame = embed.locator('iframe');
    await expect(frame).toHaveCount(1);
    await expect(frame).toHaveAttribute('sandbox', /allow-scripts/);
    await expect(frame).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    await expect(frame).toHaveAttribute('title', /.+/);
    await expect.poll(() => requests.length).toBeGreaterThan(0);
});
