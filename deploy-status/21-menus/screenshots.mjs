#!/usr/bin/env node
// Screenshots of session 21 (Menus). Run against `php spark serve` after
// `php spark app:install --env=development --db=sqlite --demo` (the Menus demo loads after Pages and News):
//   KP_ADMIN=<email> KP_ADMIN_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/21-menus/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once by the installer).
// Fails on a Content-Security-Policy violation, a page error or a horizontal page scroll.
import { chromium } from 'playwright';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/21-menus/screenshots/';
const admin = { user: process.env.KP_ADMIN || 'owner@example.com', password: process.env.KP_ADMIN_PASSWORD };
const editor = { user: 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const problems = [];

const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1280, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light', hasTouch: !!options.touch, isMobile: !!options.touch });
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

async function shot(page, name, fullPage = true) {
    await page.waitForLoadState('networkidle');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage });
    console.log('saved', name);
}

async function itemsUrl(page) {
    await page.goto(base + '/backend/menus/public');
    return page.locator('tr', { hasText: 'Menú principal' }).first().locator('a[href*="/items/"]').first().getAttribute('href');
}

{
    const { ctx, page } = await context();
    await login(page, admin);

    await page.goto(base + '/backend/menus/public');
    await shot(page, '01-public-menus');

    await page.goto(await itemsUrl(page));
    await page.waitForSelector('[data-tree-handle]:not(.d-none)');
    // Keyboard: move "Contacte" up one place (the order is not saved: the footer asks to save).
    await page.locator('[data-tree-item]', { hasText: 'Contact' }).first().locator('[data-tree-handle]').first().focus();
    await page.keyboard.press('ArrowUp');
    await shot(page, '02-items-tree-editor');

    // Drag "Contacte" back below "Notícies" with the mouse, then save.
    const handle = page.locator('[data-tree-item]', { hasText: 'Contact' }).first().locator('[data-tree-handle]').first();
    const target = page.locator('[data-tree-item]', { hasText: 'Intranet' }).first().locator('.menu-tree-row').first();
    const from = await handle.boundingBox();
    const to = await target.boundingBox();
    await page.mouse.move(from.x + 5, from.y + 5);
    await page.mouse.down();
    await page.mouse.move(to.x + 10, to.y + 5, { steps: 8 });
    await page.mouse.up();
    await Promise.all([page.waitForNavigation(), page.locator('[data-tree-save] button').click()]);

    await page.locator('[data-tree-item]', { hasText: 'News' }).first().locator('a[href*="/edit/"]').first().click();
    await page.waitForLoadState('networkidle');
    await shot(page, '03-item-form');

    await page.goto(base + '/backend/menus/backend');
    await shot(page, '04-backend-editor');

    await page.goto(base + '/backend/menus/backend/entry?key=Pages.pages&group=editor');
    await shot(page, '05-retouch-entry-for-a-group');

    await page.goto(base + '/backend/menus/backend/link/new');
    await page.fill('input[name="labels[ca]"]', 'Prova');
    await page.fill('input[name="value"]', 'javascript:alert(1)');
    await Promise.all([page.waitForNavigation(), page.locator('main form button[type="submit"]').first().click()]);
    await shot(page, '06-custom-link-refused');

    await page.goto(base + '/backend');
    await page.locator('.backend-menu .nav-group').first().click();
    await page.waitForTimeout(400);
    await shot(page, '07-admin-sidebar', false);
    await ctx.close();
}

{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/backend');
    await page.locator('.backend-menu .nav-group').first().click();
    await page.waitForTimeout(400);
    await shot(page, '08-editor-sidebar', false);
    await ctx.close();
}

{
    const { ctx, page } = await context();
    await page.goto(base + '/');
    await page.locator('.public-menu .dropdown-toggle').first().click();
    await shot(page, '09-public-menu', false);
    await ctx.close();
}

{
    const { ctx, page } = await context({ width: 390, height: 844, touch: true });
    await page.goto(base + '/qui-som/equip');
    await page.locator('.navbar-toggler').click();
    await page.waitForTimeout(400);
    await shot(page, '10-public-menu-phone', false);
    await ctx.close();
}

{
    const { ctx, page } = await context({ dark: true });
    await login(page, admin);
    await page.goto(base + '/backend/menus/backend');
    await shot(page, '11-backend-editor-dark');
    await ctx.close();
}

{
    const { ctx, page } = await context({ width: 390, height: 844, touch: true });
    await login(page, admin);
    await page.goto(await itemsUrl(page));
    await shot(page, '12-items-editor-phone');
    await ctx.close();
}

await browser.close();

if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
