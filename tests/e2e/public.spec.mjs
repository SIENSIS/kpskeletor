// The public site, as a guest: every page at every width and colour mode without horizontal
// overflow, CSP violations or axe (WCAG 2.1 AA) violations.
import { test, checkPage, checkSkipLink } from './support.mjs';

test.use({ storageState: { cookies: [], origins: [] } });

const pages = [
    ['/', 'public-home'],
    ['/news', 'public-news'],
    ['/calendar', 'public-calendar'],
    ['/login', 'login'],
    ['/login/magic-link', 'login-magic-link'],
];

for (const [url, name] of pages) {
    test(`public ${url}`, async ({ page }) => {
        await checkPage(page, url, name);
    });
}

test('public news article', async ({ page }) => {
    await page.goto('/news');
    const href = await page.locator('main a[href*="/news/"]:not([href*="/category/"]):not([href$="feed.xml"])').first().getAttribute('href');
    test.skip(!href, 'No published demo news.');
    await checkPage(page, href, 'public-news-article');
});

test('keyboard: skip link', async ({ page }) => {
    await checkSkipLink(page, '/');
});
