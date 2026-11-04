#!/usr/bin/env node
// Screenshot helper reused by every session (see deploy-status/README.md).
//
// Usage:
//   node deploy-status/screenshot.mjs <config.json>
//   node deploy-status/screenshot.mjs --out deploy-status/00-x/screenshots \
//        --base http://localhost:8080 --shot "/=01-home" --shot "/login=02-login@390x844:dark"
//
// Config file (JSON):
// {
//   "baseURL": "http://localhost:8080",
//   "outDir": "deploy-status/07-rbac/screenshots",
//   "deviceScaleFactor": 2,
//   "login": {                              // optional: default user for every shot
//     "url": "/login", "user": "admin@example.com", "password": "…",
//     "userField": "input[name=email]", "passwordField": "input[name=password]",
//     "submit": "button[type=submit]", "waitFor": "/backend"
//   },
//   "shots": [
//     { "url": "/backend", "name": "01-dashboard" },
//     { "url": "/backend", "name": "02-dashboard-phone", "width": 390, "height": 844 },
//     { "url": "/backend", "name": "03-dashboard-dark", "dark": true },
//     { "url": "/", "name": "04-home-guest", "login": false, "fullPage": true },
//     { "url": "/backend/news", "name": "05-news-editor", "login": { "user": "editor@example.com", "password": "…" } }
//   ]
// }
//
// --shot syntax: "<url>=<name>[@<width>x<height>][:dark][:full][:guest]".
// Dark mode uses prefers-color-scheme: dark and, for themes with a manual switch, sets
// localStorage "theme" = "dark" and data-bs-theme="dark" before taking the image.
// Chromium: the pre-installed browser (PLAYWRIGHT_BROWSERS_PATH) or `npx playwright install chromium`.

import { readFileSync, mkdirSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { chromium } from 'playwright';

function parseArgs(argv) {
    const cfg = { shots: [] };
    for (let i = 0; i < argv.length; i++) {
        const a = argv[i];
        const next = () => argv[++i];
        if (a === '--out') cfg.outDir = next();
        else if (a === '--base') cfg.baseURL = next();
        else if (a === '--scale') cfg.deviceScaleFactor = Number(next());
        else if (a === '--user') (cfg.login ??= {}).user = next();
        else if (a === '--password') (cfg.login ??= {}).password = next();
        else if (a === '--login-url') (cfg.login ??= {}).url = next();
        else if (a === '--shot') cfg.shots.push(parseShot(next()));
        else if (a === '--help' || a === '-h') { usage(); process.exit(0); }
        else if (!a.startsWith('--')) Object.assign(cfg, JSON.parse(readFileSync(a, 'utf8')));
        else throw new Error(`Unknown option ${a}`);
    }
    return cfg;
}

function parseShot(spec) {
    const [urlPart, rest = ''] = spec.split(/=(.*)/s);
    const [nameSize, ...flags] = rest.split(':');
    const [name, size] = nameSize.split('@');
    const shot = { url: urlPart, name: name || 'shot' };
    if (size) { const [w, h] = size.split('x').map(Number); shot.width = w; shot.height = h; }
    if (flags.includes('dark')) shot.dark = true;
    if (flags.includes('full')) shot.fullPage = true;
    if (flags.includes('guest')) shot.login = false;
    return shot;
}

function usage() {
    console.log('Usage: node deploy-status/screenshot.mjs <config.json> | --out <dir> --base <url> --shot "<url>=<name>[@WxH][:dark][:full][:guest]" [--user u --password p --login-url /login]');
}

async function logIn(context, baseURL, login) {
    const page = await context.newPage();
    await page.goto(new URL(login.url ?? '/login', baseURL).href, { waitUntil: 'networkidle' });
    await page.fill(login.userField ?? 'input[name=email]', login.user);
    await page.fill(login.passwordField ?? 'input[name=password]', login.password);
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click(login.submit ?? 'button[type=submit]'),
    ]);
    if (login.waitFor) await page.waitForURL(`**${login.waitFor}**`, { timeout: 15000 });
    await page.close();
}

async function main() {
    const cfg = parseArgs(process.argv.slice(2));
    if (!cfg.shots?.length) { usage(); process.exit(1); }
    const baseURL = cfg.baseURL ?? 'http://localhost:8080';
    const outDir = resolve(cfg.outDir ?? 'screenshots');
    const scale = cfg.deviceScaleFactor ?? 2;
    mkdirSync(outDir, { recursive: true });

    const browser = await chromium.launch();
    const contexts = new Map(); // one context (cookies) per login + colour scheme + viewport
    let failures = 0;

    try {
        for (const shot of cfg.shots) {
            const login = shot.login === false ? null : { ...(cfg.login ?? {}), ...(shot.login ?? {}) };
            const useLogin = login && login.user ? login : null;
            const width = shot.width ?? 1440;
            const height = shot.height ?? 900;
            const dark = !!shot.dark;
            const key = JSON.stringify([useLogin?.user ?? '', dark, width, height]);

            let context = contexts.get(key);
            if (!context) {
                context = await browser.newContext({
                    viewport: { width, height },
                    deviceScaleFactor: scale,
                    colorScheme: dark ? 'dark' : 'light',
                    isMobile: width < 768,
                    hasTouch: width < 768,
                    locale: cfg.locale ?? 'ca-ES',
                });
                if (dark) {
                    await context.addInitScript(() => {
                        try { localStorage.setItem('theme', 'dark'); } catch { /* storage blocked */ }
                        document.documentElement.setAttribute('data-bs-theme', 'dark');
                    });
                }
                if (useLogin) await logIn(context, baseURL, useLogin);
                contexts.set(key, context);
            }

            const page = await context.newPage();
            const target = new URL(shot.url, baseURL).href;
            try {
                const response = await page.goto(target, { waitUntil: 'networkidle' });
                if (shot.waitFor) await page.waitForSelector(shot.waitFor);
                const file = join(outDir, `${shot.name.replace(/\.png$/, '')}.png`);
                await page.screenshot({ path: file, fullPage: shot.fullPage ?? false });
                console.log(`${response?.status() ?? '---'}  ${target}  ->  ${file}`);
            } catch (err) {
                failures++;
                console.error(`FAILED  ${target}: ${err.message}`);
            } finally {
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
    process.exit(failures ? 1 : 0);
}

main().catch((err) => { console.error(err); process.exit(1); });
