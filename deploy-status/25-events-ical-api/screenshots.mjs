#!/usr/bin/env node
// Screenshots of session 25 (Events: iCal feeds and calendar API). Run against
// `php spark serve` after `php spark app:install --env=development --db=sqlite --demo`:
//   KP_OWNER=… KP_OWNER_PASSWORD=… KP_EDITOR_PASSWORD=… KP_USER_PASSWORD=… \
//     node deploy-status/25-events-ical-api/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once).
// Fails on a Content-Security-Policy violation of the application, a page error or a horizontal
// page scroll. CodeIgniter's debug toolbar (development only, `?debugbar`) is ignored: it is not
// part of the application and production never loads it.
import { chromium } from 'playwright';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/25-events-ical-api/screenshots/';
const owner = { user: process.env.KP_OWNER || 'superadmin@example.com', password: process.env.KP_OWNER_PASSWORD };
const editor = { user: 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const user = { user: 'usuari@example.com', password: process.env.KP_USER_PASSWORD };
const problems = [];
const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1366, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light', hasTouch: !!options.touch, isMobile: !!options.touch });
    if (options.dark) {
        await ctx.addCookies([{ name: 'kp_theme', value: 'dark', url: base }]);
    }
    const page = await ctx.newPage();
    page.on('console', (m) => {
        if (/Content.Security.Policy|Refused to/i.test(m.text()) && !String(m.location().url).includes('debugbar')) problems.push(m.text());
    });
    page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));
    return { ctx, page };
}

async function login(page, who) {
    await page.goto(base + '/login');
    await page.fill('input[name="email"]', who.user);
    await page.fill('input[name="password"]', who.password);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

async function shot(page, name, options = {}) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage: !!options.fullPage });
    console.log('saved', name);
}

async function calendar(page) {
    await page.waitForSelector('.events-calendar a', { timeout: 15000 });
}

// 1. The owner turns on the birthdays calendar (Settings › Users).
{
    const { ctx, page } = await context();
    await login(page, owner);
    await page.goto(base + '/backend/settings/users');
    const toggle = page.getByLabel(/Show the birthdays calendar|Mostra el calendari d'aniversaris/);
    await toggle.scrollIntoViewIfNeeded();
    if (!(await toggle.isChecked())) await toggle.check();
    await shot(page, '01-settings-birthdays');
    await Promise.all([page.waitForNavigation(), page.locator('form button[type="submit"]').last().click()]);

    // 5. The administration: a notice published by News is locked ("Managed by News").
    await page.goto(base + '/backend/events');
    await shot(page, '05-admin-managed-by-news');

    // 6. The News form: the notice's event date.
    await page.goto(base + '/backend/news/posts');
    await page.getByText('Tall del servei dissabte').first().click().catch(() => {});
    await page.goto(base + '/backend/news/posts');
    const edit = page.locator('a[href*="/backend/news/posts/edit/"]').first();
    await Promise.all([page.waitForNavigation(), edit.click()]);
    await page.locator('[name="event_starts_at"]').scrollIntoViewIfNeeded();
    await shot(page, '06-news-notice-event-date');
    await ctx.close();
}

// 2. usuari@: the calendar with the calendars toggles, a notice and birthdays.
{
    const { ctx, page } = await context();
    await login(page, user);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await shot(page, '02-calendar-calendars-toggles');

    // 3. The notice's details: managed by News, export and "Edit in News".
    await page.getByText('Tall del servei dissabte').first().click();
    await page.waitForLoadState('networkidle');
    await shot(page, '03-published-event-details');

    // 4. The subscription screen, then the new address (shown once; the login opened the re-authentication window).
    await page.goto(base + '/backend/calendar/subscription');
    await shot(page, '04-subscription-empty');
    await Promise.all([page.waitForNavigation(), page.locator('form[action*="subscription/generate"] button').click()]);
    const url = await page.inputValue('#events-ical-url');
    console.log('feed', url);
    await shot(page, '07-subscription-new-address', { fullPage: true });
    await page.goto(base + '/backend/calendar/subscription');
    await shot(page, '08-subscription-active');
    await ctx.close();
}

// 9. Phone, dark mode: the subscription screen.
{
    const { ctx, page } = await context({ width: 390, height: 844, dark: true, touch: true });
    await login(page, user);
    await page.goto(base + '/backend/calendar/subscription');
    await shot(page, '09-subscription-phone-dark', { fullPage: true });
    await ctx.close();
}

await browser.close();

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
