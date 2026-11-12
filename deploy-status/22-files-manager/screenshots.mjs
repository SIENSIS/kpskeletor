#!/usr/bin/env node
// Screenshots of session 22 (Files). Run against `php spark serve` after
// `php spark app:install --env=development --db=sqlite --demo` (or `php spark modules:demo Files`):
//   KP_ADMIN=<email> KP_ADMIN_PASSWORD=… KP_EDITOR_PASSWORD=… node deploy-status/22-files-manager/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once).
// Fails on a Content-Security-Policy violation, a page error or a horizontal page scroll.
import { chromium } from 'playwright';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/22-files-manager/screenshots/';
const admin = { user: process.env.KP_ADMIN || 'admin@example.com', password: process.env.KP_ADMIN_PASSWORD };
const editor = { user: process.env.KP_EDITOR || 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const problems = [];
const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1366, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light', hasTouch: !!options.touch, isMobile: !!options.touch });
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

async function shot(page, name, fullPage = false) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(300);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage });
    console.log('saved', name);
}

async function openFolder(page, name) {
    await page.locator('[data-fm-tree] button', { hasText: name }).first().click();
    await page.waitForLoadState('networkidle');
}

const sample = join(mkdtempSync(join(tmpdir(), 'kp22-')), 'informe.txt');
writeFileSync(sample, 'Informe de prova\n'.repeat(20000));

{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/backend/files');
    await page.waitForSelector('[data-fm-tree] button');
    await openFolder(page, 'Documents comuns');
    await page.locator('[data-fm-view="grid"]').click();
    await shot(page, '01-editor-grid');

    await page.locator('.fm-card', { hasText: 'Logotip' }).click();
    await page.waitForSelector('[data-fm-details]:not(.d-none)');
    await shot(page, '02-details-panel');

    await page.locator('[data-fm-view="list"]').click();
    await openFolder(page, 'Secretaria');
    // Upload through the file input (same code path as drag and drop) and capture the queue.
    await page.setInputFiles('[data-fm-input]', sample);
    await page.waitForSelector('.fm-queue-item');
    await shot(page, '03-upload-progress');
    await page.waitForSelector('.fm-queue-item .text-success', { timeout: 15000 });
    await page.waitForTimeout(500);
    await shot(page, '04-editor-list-after-upload');

    await page.fill('#fm-q', 'informe');
    await page.waitForTimeout(800);
    await shot(page, '05-search');
    await page.fill('#fm-q', '');
    await ctx.close();
}

{
    const { ctx, page } = await context();
    await login(page, admin);
    await page.goto(base + '/backend/files');
    await page.waitForSelector('[data-fm-tree] button');
    await openFolder(page, 'Secretaria');
    await page.locator('[data-fm-acl]').click();
    await page.waitForSelector('.modal.show');
    await shot(page, '06-permissions-dialog');
    await page.locator('.modal.show .btn-secondary').click();
    await page.waitForTimeout(400);

    await openFolder(page, 'Actes');
    await page.locator('[data-fm-acl]').click();
    await page.waitForSelector('.modal.show');
    await shot(page, '07-permissions-override');
    await page.locator('.modal.show .btn-secondary').click();
    await page.waitForTimeout(400);

    // Trash a file and show the trash.
    await openFolder(page, 'Circulars');
    await page.locator('[data-fm-listing] tbody button').first().click();
    await page.locator('[data-fm-details] .btn-outline-danger').click();
    await page.locator('.modal.show .btn-danger').click();
    await page.waitForTimeout(600);
    await page.locator('[data-fm-trash]').click();
    await page.waitForTimeout(600);
    await shot(page, '08-trash');
    await ctx.close();
}

{
    const { ctx, page } = await context({ width: 390, height: 844, touch: true });
    await login(page, editor);
    await page.goto(base + '/backend/files');
    await page.waitForSelector('[data-fm-tree] button', { state: 'attached' });
    await page.locator('[data-fm-toggle-tree]').click();
    await shot(page, '09-phone-tree');
    await openFolder(page, 'Documents comuns');
    await shot(page, '10-phone-list');
    await ctx.close();
}

{
    const { ctx, page } = await context({ dark: true });
    await login(page, editor);
    await page.goto(base + '/backend/files');
    await page.waitForSelector('[data-fm-tree] button');
    await openFolder(page, 'Documents comuns');
    await page.locator('[data-fm-view="grid"]').click();
    await shot(page, '11-dark-grid');
    await ctx.close();
}

await browser.close();

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}
