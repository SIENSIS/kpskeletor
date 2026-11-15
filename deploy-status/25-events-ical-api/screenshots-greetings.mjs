#!/usr/bin/env node
// Screenshots of D-25-09 (birthday greetings). Run against `php spark serve` after a fresh
// `app:install --demo`, with the editor's birthday set to today, the Editor group greeted and
// `php spark schedule:run --task=SendBirthdayGreetings --force`:
//   KP_OWNER=… KP_OWNER_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/25-events-ical-api/screenshots-greetings.mjs
import { chromium } from 'playwright';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/25-events-ical-api/screenshots/';
const owner = { user: process.env.KP_OWNER || 'superadmin@example.com', password: process.env.KP_OWNER_PASSWORD };
const editor = { user: 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const problems = [];
const browser = await chromium.launch();

async function context() {
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
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

async function shot(page, name) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    if (await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1)) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png' });
    console.log('saved', name);
}

// 17–18. The management: the greetings settings and the group switch.
{
    const { ctx, page } = await context();
    await login(page, owner);
    await page.goto(base + '/backend/settings/users');
    await page.getByText(/Greet only users who share their birthday|Felicita només/).first().scrollIntoViewIfNeeded();
    await shot(page, '17-settings-birthday-greetings');
    await page.goto(base + '/backend/users/groups');
    await Promise.all([page.waitForNavigation(), page.locator('a[href*="/backend/users/groups/edit/"]').nth(2).click()]);
    await page.locator('#birthday_greet').scrollIntoViewIfNeeded();
    await shot(page, '18-group-greet-switch');
    await ctx.close();
}

// 19. The editor: the greeting in the bell.
{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/backend/notifications');
    await shot(page, '19-birthday-greeting-notification');
    await ctx.close();
}

await browser.close();

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
