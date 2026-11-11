#!/usr/bin/env node
// Screenshots of session 20 (Pages). Run against `php spark serve` after
// `php spark app:install --env=development --db=sqlite --demo`:
//   KP_ADMIN=<email> KP_ADMIN_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/20-pages/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once by the installer).
// Fails on a Content-Security-Policy violation or a horizontal page scroll, like screenshot.mjs.
import { chromium } from 'playwright';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/20-pages/screenshots/';
const admin = { user: process.env.KP_ADMIN || 'owner@example.com', password: process.env.KP_ADMIN_PASSWORD };
const editor = { user: 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const problems = [];

const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1280, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light' });
    if (options.dark) {
        await ctx.addCookies([{ name: 'kp_theme', value: 'dark', url: base }]);
    }
    const page = await ctx.newPage();
    page.on('console', (m) => { if (/Content.Security.Policy|Refused to/i.test(m.text())) problems.push(m.text()); });
    page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));
    return { ctx, page };
}

async function login(page, who) {
    await page.goto(base + '/login');
    await page.fill('input[name="email"]', who.user);
    await page.fill('input[name="password"]', who.password);
    await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

async function action(page, rowText, operation) {
    await page.goto(base + '/backend/pages');
    const href = await page.locator('tr', { hasText: rowText }).first().locator(`a[href*="/${operation}/"]`).first().getAttribute('href');
    await page.goto(href);
}

async function shot(page, name, fullPage = true) {
    await page.waitForLoadState('networkidle');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage });
    console.log('saved', name);
}

// Back office as the administrator.
{
    const { ctx, page } = await context();
    await login(page, admin);
    await page.goto(base + '/backend/pages');
    await shot(page, '01-pages-list');

    // Edit "Qui som": the editor, then save a change (a new revision).
    await action(page, 'Qui som', 'edit');
    await page.waitForSelector('.kp-editor .ql-editor');
    await page.locator('.kp-editor .ql-editor').first().click();
    await page.keyboard.press('Control+Home');
    await page.keyboard.type('Des del 2024 també oferim formació en línia.');
    await page.keyboard.press('Enter');
    await shot(page, '02-page-form-editor');
    await Promise.all([page.waitForNavigation(), page.locator('main form button[type="submit"]').first().click()]);

    await action(page, 'Qui som', 'revisions');
    await shot(page, '03-revisions');

    // Settings › Pages: the home page.
    await page.goto(base + '/backend/settings/pages');
    await page.selectOption('select[name$="homePage"]', { index: 1 }); // "Inici" ("Home" in English), the first page of the tree
    await shot(page, '04-settings-home-page');
    await Promise.all([page.waitForNavigation(), page.locator('main form button[type="submit"]').first().click()]);

    // Preview of a draft.
    await action(page, 'preparaci', 'preview');
    await shot(page, '05-preview-draft');

    // News uses the same editor.
    await page.goto(base + '/backend/news/posts/add');
    await page.waitForSelector('.kp-editor .ql-editor');
    await shot(page, '06-news-editor');
    await ctx.close();
}

// Dark mode and phone width in the back office.
{
    const { ctx, page } = await context({ dark: true });
    await login(page, admin);
    await page.goto(base + '/backend/pages/add');
    await page.waitForSelector('.kp-editor .ql-editor');
    await page.fill('#kp-tr-ca-title', 'Nova pàgina');
    await page.locator('.kp-editor .ql-editor').first().click();
    await page.keyboard.type('Text en mode fosc');
    await shot(page, '07-page-form-dark');
    await ctx.close();
}
{
    const { ctx, page } = await context({ width: 390, height: 844 });
    await login(page, admin);
    await page.goto(base + '/backend/pages');
    await shot(page, '08-pages-list-phone');
    await ctx.close();
}

// Public site as a guest.
{
    const { ctx, page } = await context();
    await page.goto(base + '/');
    await shot(page, '09-public-home');
    await page.goto(base + '/qui-som');
    await shot(page, '10-public-sidebar-template');
    await page.goto(base + '/intranet');
    await shot(page, '11-private-page-asks-login');
    await ctx.close();
}
{
    const { ctx, page } = await context({ width: 390, height: 844 });
    await page.goto(base + '/qui-som/equip');
    await shot(page, '12-public-phone');
    await ctx.close();
}
{
    const { ctx, page } = await context({ dark: true });
    await page.goto(base + '/qui-som');
    await shot(page, '13-public-dark');
    await ctx.close();
}

// A group page for the editor.
{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/intranet/guia-estil');
    await shot(page, '14-group-page-editor');
    await ctx.close();
}

await browser.close();

if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
