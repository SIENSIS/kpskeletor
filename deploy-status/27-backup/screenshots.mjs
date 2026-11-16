#!/usr/bin/env node
// Screenshots of session 27 (Backup module). Run against `php spark serve` after
// `php spark app:install --env=development --db=sqlite --demo` and a few backups
// (`BACKUP_PASSWORD=… php spark backup:create`, `--only=db`):
//   KP_OWNER=… KP_OWNER_PASSWORD=… KP_BACKUP_PASSWORD=… KP_JOB=<id> node deploy-status/27-backup/screenshots.mjs
// KP_JOB: a job file in writable/backups/jobs with status "running" (the progress of a restore
// cannot be caught under `php spark serve`, which runs the job inside the request).
// Secrets on the restore result page are masked before the capture.
// Fails on a Content-Security-Policy violation, a page error or a horizontal page scroll.
import { createRequire } from 'node:module';

const { chromium } = createRequire(import.meta.url)('playwright');

const base = process.env.KP_BASE || 'http://localhost:8080';
const out = 'deploy-status/27-backup/screenshots/';
const owner = { user: process.env.KP_OWNER || 'root@example.com', password: process.env.KP_OWNER_PASSWORD };
const backupPassword = process.env.KP_BACKUP_PASSWORD || '';
const job = process.env.KP_JOB || '';
const problems = [];
const browser = await chromium.launch();

async function context(options = {}) {
    const ctx = await browser.newContext({ viewport: { width: options.width || 1366, height: options.height || 900 }, deviceScaleFactor: 2, colorScheme: options.dark ? 'dark' : 'light' });
    if (options.dark) {
        await ctx.addCookies([{ name: 'kp_theme', value: 'dark', url: base }]);
    }
    const page = await ctx.newPage();
    page.on('console', (m) => {
        if (/Content.Security.Policy|Refused to/i.test(m.text()) && !String(m.location().url).includes('debugbar')) problems.push(m.text());
    });
    page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));
    if (options.login !== false) {
        await page.goto(base + '/login');
        await page.fill('input[name="email"]', owner.user);
        await page.fill('input[name="password"]', owner.password);
        await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    }
    return { ctx, page };
}

async function shot(page, name, options = {}) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    if (overflow) problems.push(`${name}: horizontal scroll`);
    await page.screenshot({ path: out + name + '.png', fullPage: !!options.fullPage });
    console.log('saved', name);
}

{
    const { ctx, page } = await context();

    // 1. The list: console, scheduled-like, back office and safety backups.
    await page.goto(base + '/backend/backups');
    await shot(page, '01-backups-list');

    // 2. Create a backup (validation: password too short).
    await page.goto(base + '/backend/backups/create');
    await page.fill('#backup-password', 'short');
    await page.fill('#backup-password-confirm', 'short');
    await Promise.all([page.waitForNavigation(), page.click('form.form-layout button[type="submit"]')]);
    await shot(page, '02-create-refused');

    // 3. Verify the newest backup with its password: the contents.
    await page.goto(base + '/backend/backups');
    const verify = await page.locator('a[href*="/backend/backups/verify/"]').first().getAttribute('href');
    await page.goto(verify);
    await page.fill('#backup-password', backupPassword);
    await Promise.all([page.waitForNavigation(), page.click('form.form-layout button[type="submit"]')]);
    await shot(page, '03-verify-contents');

    // 4. Restore form: warning, parts, typed confirmation (wrong name typed).
    await page.goto(base + '/backend/backups');
    const restore = await page.locator('a[href*="/backend/backups/recover/"]').first().getAttribute('href');
    await page.goto(restore);
    await page.fill('#backup-password', backupPassword);
    await page.fill('#backup-confirm', 'backup.kpbackup');
    await Promise.all([page.waitForNavigation(), page.click('form.form-layout button[type="submit"]')]);
    await shot(page, '04-restore-confirmation');

    // 5. A restore in progress (a job file in the "running" state).
    if (job !== '') {
        await page.goto(base + '/backend/backups/jobs/' + job);
        await page.waitForTimeout(1200);
        await shot(page, '05-restore-progress');
    }

    // 6. A real restore: the result page (the merged .env is masked for the image).
    await page.goto(restore);
    const file = (await page.locator('main code').first().textContent()).trim();
    await page.fill('#backup-password', backupPassword);
    await page.fill('#backup-confirm', file);
    await Promise.all([page.waitForNavigation({ timeout: 120000 }), page.click('form.form-layout button[type="submit"]')]);
    await page.evaluate(() => {
        const area = document.querySelector('#backup-env');
        if (area) area.value = area.value.replace(/=.*$/gm, '= ••••••••');
    });
    await shot(page, '06-restore-result');
    await ctx.close();
}

// 7. The dashboard widget.
{
    const { ctx, page } = await context();
    await page.goto(base + '/backend');
    const card = page.locator('[data-widget="backup.backups"], .card:has-text("Backups")').first();
    await card.scrollIntoViewIfNeeded();
    await shot(page, '07-dashboard-widget');
    await ctx.close();
}

// 8. Phone width and 9. dark mode.
{
    const { ctx, page } = await context({ width: 390, height: 844 });
    await page.goto(base + '/backend/backups');
    await shot(page, '08-backups-phone');
    await ctx.close();
}
{
    const { ctx, page } = await context({ dark: true });
    await page.goto(base + '/backend/backups');
    await shot(page, '09-backups-dark');
    await ctx.close();
}

await browser.close();

if (problems.length > 0) {
    console.error(problems.join('\n'));
    process.exit(1);
}
