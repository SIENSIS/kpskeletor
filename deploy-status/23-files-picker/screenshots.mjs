#!/usr/bin/env node
// Screenshots of session 23 (file picker, upload tokens, avatars). Run against `php spark serve`
// after `php spark app:install --env=development --db=sqlite --demo`:
//   KP_EDITOR_PASSWORD=… KP_USER_PASSWORD=… KP_IMAGES=<folder with avatar.png, photo.jpg> \
//     node deploy-status/23-files-picker/screenshots.mjs
// Passwords come from the environment (the demo passwords are random, shown once).
// Fails on a Content-Security-Policy violation, a page error or a horizontal page scroll.
import { chromium } from 'playwright';
import { join } from 'node:path';

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/23-files-picker/screenshots/';
const images = process.env.KP_IMAGES;
const editor = { user: process.env.KP_EDITOR || 'editor@example.com', password: process.env.KP_EDITOR_PASSWORD };
const user = { user: process.env.KP_USER || 'usuari@example.com', password: process.env.KP_USER_PASSWORD };
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

async function shot(page, name, options = {}) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage: !!options.fullPage });
    console.log('saved', name);
}

// 1–2. A user without any files.* permission uploads an avatar from the profile.
{
    const { ctx, page } = await context();
    await login(page, user);
    await page.goto(base + '/backend/profile');
    await page.waitForSelector('.file-picker[data-file-picker="ready"]');
    await page.setInputFiles('[data-picker-input]', join(images, 'avatar.png'));
    await page.waitForSelector('.file-picker-item[data-key]');
    await shot(page, '01-profile-avatar-uploaded');
    await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]', { hasText: /Desa/ }).first().click()]);
    await page.waitForSelector('img.avatar');
    await shot(page, '02-profile-avatar-saved');
    const src = await page.locator('img.avatar').first().getAttribute('src');
    if (!src || !src.includes('/files/avatar/')) problems.push('avatar URL not used: ' + src);
    await ctx.close();
}

// 3–6. An editor: cover picker, "Insert image" in the body, the result on the public site.
{
    const { ctx, page } = await context();
    await login(page, editor);
    await page.goto(base + '/backend/news/posts/add');
    await page.waitForSelector('#field-cover[data-file-picker="ready"]');
    await page.fill('input[name="kp_tr[ca][title]"]', 'Jornada de portes obertes');
    await page.setInputFiles('#field-cover [data-picker-input]', join(images, 'photo.jpg'));
    await page.waitForSelector('#field-cover .file-picker-item[data-key]');
    await page.locator('#field-cover [data-picker-browse]').click();
    await page.waitForSelector('.modal.show .file-picker-choice');
    await shot(page, '03-cover-choose-dialog');
    await page.locator('.modal.show .btn-secondary', { hasText: /Cancel/ }).click();
    await page.waitForSelector('.modal', { state: 'detached' });

    await page.locator('.ql-image').first().click();
    await page.waitForSelector('.modal.show input[type="file"]');
    await page.setInputFiles('.modal.show input[type="file"]', join(images, 'photo.jpg'));
    await page.waitForSelector('.modal.show .file-picker-choice[aria-pressed="true"]');
    await page.fill('.modal.show input[type="text"]', 'Alumnes al pati de l’escola');
    await shot(page, '04-insert-image-dialog');
    await page.locator('.modal.show .btn-primary').click();
    await page.waitForSelector('.ql-editor img');
    await page.locator('.kp-editor').first().scrollIntoViewIfNeeded();
    await shot(page, '05-editor-with-image');
    await page.selectOption('select[name="visibility"]', 'public');
    await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]', { hasText: /Desa/ }).first().click()]);
    await page.waitForLoadState('networkidle');
    await ctx.close();
}

// 7. Phone width: the avatar field.
{
    const { ctx, page } = await context({ width: 390, height: 844, touch: true });
    await login(page, user);
    await page.goto(base + '/backend/profile');
    await page.waitForSelector('.file-picker[data-file-picker="ready"]');
    await page.locator('.file-picker').scrollIntoViewIfNeeded();
    await shot(page, '06-phone-profile-avatar');
    await ctx.close();
}

// 8. Dark mode: the "Insert image" dialog.
{
    const { ctx, page } = await context({ dark: true });
    await login(page, editor);
    await page.goto(base + '/backend/news/posts/add');
    await page.waitForSelector('.ql-image');
    await page.locator('.ql-image').first().click();
    await page.waitForSelector('.modal.show .file-picker-choice');
    await page.locator('.modal.show .file-picker-choice').first().click();
    await shot(page, '07-dark-insert-image');
    await ctx.close();
}

await browser.close();

if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
console.log('no CSP violation, page error or horizontal scroll');
