#!/usr/bin/env node
// Screenshots of session 17 (News). Needs `php spark serve` with the Auth and News demo data.
// Usage: node deploy-status/17-news/screenshots.mjs [baseURL]
// Pages are reached by clicking their links (masked ids differ per installation). Fails on a
// horizontal page scroll or a Content-Security-Policy violation, like deploy-status/screenshot.mjs.
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const base = process.argv[2] ?? 'http://localhost:8080';
const out = 'deploy-status/17-news/screenshots';
const password = 'Demo-kpskeletor-2026';
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();

async function session(user, { width = 1366, height = 900, dark = false } = {}) {
    const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, colorScheme: dark ? 'dark' : 'light' });
    if (dark) {
        await context.addInitScript(() => {
            try { localStorage.setItem('theme', 'dark'); } catch { /* storage blocked */ }
            const apply = () => document.documentElement?.setAttribute('data-bs-theme', 'dark');
            apply();
            document.addEventListener('DOMContentLoaded', apply);
        });
    }
    const page = await context.newPage();
    const violations = [];
    page.on('console', (msg) => { if (/Content.Security.Policy/i.test(msg.text())) violations.push(msg.text()); });
    page.on('pageerror', (err) => violations.push(String(err)));
    if (user) {
        await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
        await page.fill('input[name=email]', user);
        await page.fill('input[name=password]', password);
        await Promise.all([page.waitForURL(/\/backend/), page.click('button[type=submit]')]);
    }
    return { context, page, violations };
}

async function shot(s, name, fullPage = true) {
    await s.page.waitForLoadState('networkidle');
    const overflow = await s.page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 0) throw new Error(`${name}: horizontal overflow of ${overflow}px`);
    if (s.violations.length) throw new Error(`${name}: ${s.violations.join(' | ')}`);
    await s.page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(`${name}.png`);
}

// Ordinary user: dashboard with the News widgets, reader screen, a notice, mark as read.
let s = await session('usuari@example.com');
await s.page.goto(`${base}/backend`);
await shot(s, '01-dashboard-user');
await s.page.goto(`${base}/backend/news`);
await shot(s, '02-reader');
await s.page.click('a:has-text("Tall del servei dissabte")');
await shot(s, '03-reader-notice');
await s.page.click('form button:has-text("Marca com a llegida")');
await shot(s, '04-marked-read');
await s.context.close();

// Editor: the group notice on the dashboard, "My posts".
s = await session('editor@example.com');
await s.page.goto(`${base}/backend/news/mine`);
await shot(s, '05-my-posts-editor');
await s.context.close();

// Administrator: all posts, the form, readers, categories.
s = await session('admin@example.com');
await s.page.goto(`${base}/backend/news/posts`);
await shot(s, '06-posts-admin');
await s.page.goto(`${base}/backend/news/posts`);
await s.page.click('tr:has-text("Tall del servei dissabte") a[href*="/backend/news/posts/edit/"]');
await shot(s, '07-post-form');
await s.page.goto(`${base}/backend/news/posts`);
await s.page.click('tr:has-text("Tall del servei dissabte") a[href*="/backend/news/posts/readers/"]');
await shot(s, '08-readers');
await s.page.goto(`${base}/backend/news/categories`);
await shot(s, '09-categories');
await s.context.close();

// Public site (guest).
s = await session(null);
await s.page.goto(`${base}/news`);
await shot(s, '10-public-list');
await s.page.click('a:has-text("Benvinguda al nou portal")');
await shot(s, '11-public-post');
await s.context.close();

// Phone and dark mode.
s = await session('usuari@example.com', { width: 390, height: 844 });
await s.page.goto(`${base}/backend/news`);
await shot(s, '12-reader-phone');
await s.context.close();
s = await session('usuari@example.com', { dark: true });
await s.page.goto(`${base}/backend`);
await shot(s, '13-dashboard-dark');
await s.context.close();

await browser.close();
