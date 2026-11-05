#!/usr/bin/env node
// Screenshots of session 05 (run against `php spark serve` with `php spark modules:demo Auth`):
//   node deploy-status/05-auth/screenshots.mjs
// The flows need several steps (two browsers, a captcha setting, an idle lock), so this script
// drives Playwright directly instead of deploy-status/screenshot.mjs.
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const base = 'http://localhost:8080';
const out = 'deploy-status/05-auth/screenshots';
const password = 'Demo-kpskeletor-2026';
const setting = (key, value) => execFileSync('php', ['deploy-status/05-auth/settings.php', key, ...(value === undefined ? [] : [String(value)])]);
const violations = [];

mkdirSync(out, { recursive: true });
const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ deviceScaleFactor: 2, viewport: { width: 1280, height: 800 }, locale: 'ca-ES', ...options });
    ctx.on('page', (page) => page.on('console', (msg) => {
        if (/Content.Security.Policy/i.test(msg.text())) violations.push(msg.text());
    }));
    return ctx;
}

async function shot(page, name, options = {}) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    if (overflow) throw new Error(`${name}: horizontal scroll`);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: options.fullPage ?? false });
    console.log(`${name}.png`);
}

async function login(page, email) {
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForURL('**/backend/profile'), page.click('form button[type=submit]')]);
}

setting('captcha');
setting('idleTimeout');

// Another device signed in, with an app token, so "my sessions" has content.
const phone = await context({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1' });
await login(await phone.newPage(), 'superadmin@example.com');

const desk = await context();
let page = await desk.newPage();
await page.goto(`${base}/login`);
await shot(page, '01-login');

await page.fill('input[name=email]', 'superadmin@example.com');
await page.fill('input[name=password]', 'wrong-password');
await page.click('form button[type=submit]');
await shot(page, '02-login-generic-error');

setting('captcha', 1);
await page.goto(`${base}/login`);
await shot(page, '03-login-captcha');
setting('captcha');

await page.goto(`${base}/login/magic-link`);
await shot(page, '04-magic-link');
await page.fill('input[name=email]', 'nobody@example.com');
await page.click('form button[type=submit]');
await shot(page, '05-link-sent');

await page.goto(`${base}/reset-password`);
await shot(page, '06-reset-password');

await login(page, 'superadmin@example.com');
await shot(page, '07-profile', { fullPage: true });

await page.goto(`${base}/backend/profile/password`);
await shot(page, '08-password');

await page.goto(`${base}/backend/profile/sessions`);
await shot(page, '09-my-sessions', { fullPage: true });

await page.click('header .dropdown-toggle');
await shot(page, '10-user-menu');

const mobile = await context({ viewport: { width: 390, height: 844 }, colorScheme: 'dark' });
const small = await mobile.newPage();
await small.goto(`${base}/login`);
await shot(small, '11-login-phone-dark');
await login(small, 'usuari@example.com');
await small.goto(`${base}/backend/profile/sessions`);
await shot(small, '12-my-sessions-phone-dark', { fullPage: true });

// Idle lock: one minute without requests.
setting('idleTimeout', 1);
const idle = await context();
page = await idle.newPage();
await login(page, 'editor@example.com');
await page.waitForTimeout(62_000);
await page.goto(`${base}/backend/profile/sessions`);
await shot(page, '13-idle-lock');
setting('idleTimeout');

await browser.close();

if (violations.length > 0) {
    console.error('CSP violations:', violations);
    process.exit(1);
}
