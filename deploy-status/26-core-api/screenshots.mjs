#!/usr/bin/env node
// Screenshots of session 26 (Core API endpoints). Run against `php spark serve` after
// `php spark app:install --env=development --db=sqlite --demo`:
//   KP_OWNER=… KP_OWNER_PASSWORD=… KP_TOKEN=… node deploy-status/26-core-api/screenshots.mjs
// KP_TOKEN: a token of usuari@example.com (`php spark kpapy:token usuari@example.com --days 1`);
// it is typed into the masked token field of "Try it" and never appears in an image.
// Fails on a Content-Security-Policy violation, a page error or a horizontal page scroll.
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/26-core-api/screenshots/';
const owner = { user: process.env.KP_OWNER || 'superadmin@example.com', password: process.env.KP_OWNER_PASSWORD };
const token = process.env.KP_TOKEN || '';
const problems = [];
const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1366, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light' });
    if (options.dark) {
        await ctx.addCookies([{ name: 'kp_theme', value: 'dark', url: base }]);
    }
    const page = await ctx.newPage();
    page.on('console', (m) => {
        if (/Content.Security.Policy|Refused to/i.test(m.text()) && !String(m.location().url).includes('debugbar')) problems.push(m.text());
    });
    page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));
    await page.goto(base + '/login');
    await page.fill('input[name="email"]', owner.user);
    await page.fill('input[name="password"]', owner.password);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    return { ctx, page };
}

async function shot(page, name, options = {}) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage: !!options.fullPage, clip: options.clip });
    console.log('saved', name);
}

function endpoint(page, method, path) {
    return page.locator('details').filter({ has: page.locator(`summary .path:text-is("${path}")`) }).filter({ has: page.locator(`summary .m:text-is("${method}")`) }).first();
}

async function open(page, method, path) {
    const details = endpoint(page, method, path);
    await details.locator('summary').click();
    await details.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollBy(0, -60));
    return details;
}

async function send(page, details, extra = '') {
    if (extra !== '') await details.locator('input[name="extra"]').fill(extra);
    await details.locator('button[type="submit"]').click();
    await details.locator('[data-kp-result]:not([hidden])').waitFor();
    await page.waitForTimeout(300);
    await details.scrollIntoViewIfNeeded();
}

{
    const { ctx, page } = await context();
    await page.goto(base + '/backend/api/docs');

    // 1. The documentation page: the Core endpoints listed (sections collapsed).
    await shot(page, '01-api-docs-overview');

    // 2. The events, news and notifications endpoints (collapsed).
    await page.locator('h2').filter({ hasText: /^Events$/ }).first().evaluate((h) => h.scrollIntoView({ block: 'start' }));
    await shot(page, '02-api-docs-endpoints');

    // 3. Public news without a token: "Try it" sends a real request.
    let details = await open(page, 'GET', '/api/v1/public/news');
    await send(page, details, 'per_page=2&fields=title,category,published_at');
    await shot(page, '03-try-public-news');
    await details.locator('summary').click();

    // 4. With usuari@'s token: the occurrences of the next two weeks (events, News notices).
    if (token !== '') {
        await page.locator('input[name="token"]').fill(token);
        details = await open(page, 'GET', '/api/v1/events/occurrences');
        const start = new Date();
        const end = new Date(Date.now() + 14 * 86400000);
        await send(page, details, `start=${start.toISOString().slice(0, 10)}&end=${end.toISOString().slice(0, 10)}`);
        await shot(page, '04-try-events-occurrences');
        await details.locator('summary').click();

        // 5. The "mark as read" operation (news-api.read) in the documentation.
        details = await open(page, 'POST', '/api/v1/news/{id}/read');
        await shot(page, '05-news-read-operation');
        await details.locator('summary').click();
    }
    await ctx.close();
}

// 6. Phone width and 7. dark mode of the documentation page.
{
    const { ctx, page } = await context({ width: 390, height: 844 });
    await page.goto(base + '/backend/api/docs');
    await page.locator('h2').filter({ hasText: /^Notifications$/ }).first().evaluate((h) => h.scrollIntoView({ block: 'start' }));
    await shot(page, '06-api-docs-phone');
    await ctx.close();
}
{
    const { ctx, page } = await context({ dark: true });
    await page.goto(base + '/backend/api/docs');
    await page.locator('h2').filter({ hasText: /^Events$/ }).first().evaluate((h) => h.scrollIntoView({ block: 'start' }));
    await shot(page, '07-api-docs-dark');
    await ctx.close();
}

// 8. The permission matrix: the new API permissions (every group).
{
    const { ctx, page } = await context();
    await page.goto(base + '/backend/users/permissions');
    const row = page.getByText(/Calendar API|API del calendari/).first();
    await row.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollBy(0, -120));
    await shot(page, '08-permission-matrix');
    await ctx.close();
}

await browser.close();

if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
