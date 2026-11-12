#!/usr/bin/env node
// Screenshots of session 24 (Events: calendar, administration, audiences). Run against
// `php spark serve` after `php spark app:install --env=development --db=sqlite --demo`:
//   KP_OWNER=… KP_OWNER_PASSWORD=… KP_EDITOR_PASSWORD=… KP_USER_PASSWORD=… \
//     node deploy-status/24-events-core/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once).
// Fails on a Content-Security-Policy violation of the application, a page error or a horizontal
// page scroll. CodeIgniter's debug toolbar (development only, `?debugbar`) is ignored: it is not
// part of the application and production never loads it.
import { chromium } from 'playwright';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/24-events-core/screenshots/';
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

// 1. usuari@: the month view with "Entrevista personal" (an event only for them).
{
    const { ctx, page } = await context();
    await login(page, user);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await shot(page, '01-calendar-month-user');

    // 4. The details of their personal event, with a reminder.
    await page.getByText('Entrevista personal').first().click();
    await page.waitForLoadState('networkidle');
    await page.selectOption('#events-reminder', '60');
    await Promise.all([page.waitForNavigation(), page.click('form[action*="/reminder"] button[type="submit"]')]);
    await shot(page, '04-event-details-reminder');

    // 8. The dashboard widget.
    await page.goto(base + '/backend');
    await shot(page, '08-dashboard-upcoming-widget');
    await ctx.close();
}

// 2. The superadmin's calendar: the event only for usuari@ is not there; week view.
{
    const { ctx, page } = await context();
    await login(page, owner);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await page.locator('.events-calendar button', { hasText: /^(Setmana|Week|Semana)$/ }).first().click();
    await page.waitForTimeout(500);
    await shot(page, '02-calendar-week-superadmin');

    // 5. The administration list (events-events.all).
    await page.goto(base + '/backend/events');
    await shot(page, '05-admin-list');
    await ctx.close();
}

// 3. Phone, dark mode: the list view.
{
    const { ctx, page } = await context({ width: 390, height: 844, dark: true, touch: true });
    await login(page, user);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await shot(page, '03-calendar-phone-dark');
    await ctx.close();
}

// 6. editor@: the form offers only the groups they may assign (Editor, User).
{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/backend/events/add');
    await page.selectOption('select[name="visibility"]', 'restricted');
    await page.locator('select[name="groups[]"]').scrollIntoViewIfNeeded();
    await shot(page, '06-editor-form-assignable-groups');
    await ctx.close();
}

// 7. The public calendar (guest): public events only.
{
    const { ctx, page } = await context();
    await page.goto(base + '/calendar');
    await calendar(page);
    await shot(page, '07-public-calendar');
    await ctx.close();
}

// 9. Dark mode, desktop: the back-office calendar of the editor (their group's event).
{
    const { ctx, page } = await context({ dark: true });
    await login(page, editor);
    await page.goto(base + '/backend/calendar');
    await calendar(page);
    await shot(page, '09-calendar-dark-editor');
    await ctx.close();
}

await browser.close();

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
