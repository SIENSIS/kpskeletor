#!/usr/bin/env node
// Screenshots of Decisions 25 (D-25-08: birthdays privacy and calendar switches). Run against
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

// 10–13. The management (owner, admin): settings on, calendar switches, "only" the administrative calendar, a group's audiences, a record.
{
    const { ctx, page } = await context();
    await login(page, owner);
    await page.goto(base + '/backend/settings/users');
    const toggle = page.getByLabel(/Show the birthdays calendar|Mostra el calendari d'aniversaris/);
    if (!(await toggle.isChecked())) await toggle.check();
    await Promise.all([page.waitForNavigation(), page.locator('form button[type="submit"]').last().click()]);

    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await page.locator('#events-calendars').scrollIntoViewIfNeeded();
    await shot(page, '10-calendar-switches-admin-hidden');
    await page.locator('button[name="only"][value="users.birthdaysadmin"]').click();
    await page.waitForTimeout(1200);
    await shot(page, '11-calendar-only-administrative-birthdays');
    await page.locator('button[name="all"]').click();
    await page.waitForTimeout(800);

    await page.goto(base + '/backend/users/groups');
    const edit = page.locator('a[href*="/backend/users/groups/edit/"]').nth(2);
    await Promise.all([page.waitForNavigation(), edit.click()]);
    await page.locator('[name="birthday_groups[]"]').first().scrollIntoViewIfNeeded().catch(() => {});
    await shot(page, '12-group-sees-birthdays-of');

    await page.goto(base + '/backend/users');
    const show = page.locator('a[href*="/backend/users/show/"]').last();
    await Promise.all([page.waitForNavigation(), show.click()]);
    await shot(page, '13-user-record-date-of-birth');
    await ctx.close();
}

// 14. usuari@: the profile's consent.
{
    const { ctx, page } = await context();
    await login(page, user);
    await page.goto(base + '/backend/profile');
    await page.locator('#share_birthday').scrollIntoViewIfNeeded();
    await shot(page, '14-profile-share-birthday');
    await ctx.close();
}

// 15. Phone, dark mode: the switches.
{
    const { ctx, page } = await context({ width: 390, height: 844, dark: true, touch: true });
    await login(page, editor);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await shot(page, '15-calendar-switches-phone-dark');
    await ctx.close();
}

await browser.close();

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
