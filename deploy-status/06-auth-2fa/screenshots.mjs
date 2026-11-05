import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const ROOT = '/home/user/kpskeletor';
const OUT = ROOT + '/deploy-status/06-auth-2fa/screenshots';
const BASE = 'http://localhost:8080';
const PASS = 'Demo-kpskeletor-2026';
mkdirSync(OUT, { recursive: true });
const totp = (secret, offset = 0) => execFileSync('php', [process.env.S + '/totp.php', ROOT, secret, String(offset)]).toString().trim();

const browser = await chromium.launch();
const violations = [];
async function ctx(opts = {}) {
    const c = await browser.newContext({ viewport: { width: opts.width ?? 1280, height: opts.height ?? 820 }, deviceScaleFactor: 2, colorScheme: opts.dark ? 'dark' : 'light' });
    const p = await c.newPage();
    p.on('console', m => { if (/Content.Security.Policy/i.test(m.text())) violations.push(m.text()); });
    return p;
}
async function shot(p, name, full = false) {
    const overflow = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    if (overflow) throw new Error('Horizontal overflow on ' + name);
    await p.screenshot({ path: `${OUT}/${name}.png`, fullPage: full });
    console.log('saved', name, p.url());
}
async function login(p, user) {
    await p.goto(BASE + '/login');
    await p.fill('input[name=email]', user);
    await p.fill('input[name=password]', PASS);
    await p.click('form[action$="/login"] button[type=submit]');
    await p.waitForLoadState('networkidle');
}

const p = await ctx();
await login(p, 'usuari@example.com');
await p.goto(BASE + '/backend/profile/two-factor');
await shot(p, '01-two-factor-off', true);

await p.goto(BASE + '/backend/profile/two-factor/app');
await shot(p, '02-app-enrolment', true);
const secret = (await p.textContent('.user-select-all')).replace(/\s+/g, '');
await p.fill('#code', totp(secret, -30));
await p.click('form.form-layout button[type=submit]');
await p.waitForLoadState('networkidle');
await shot(p, '03-recovery-codes', true);

// Log out and log in again: the code step.
await p.goto(BASE + '/logout');
await p.click('form[action$="/logout"] button[type=submit]');
await login(p, 'usuari@example.com');
await shot(p, '04-login-app-code');
await p.click('details summary');
await shot(p, '05-login-recovery-code');
await p.fill('#code', totp(secret, 30));
await p.click('form[action$="/auth/a/verify"] button[type=submit]');
await p.waitForLoadState('networkidle');
console.log('after code:', p.url());

await p.goto(BASE + '/auth/reauth');
await shot(p, '06-reauth');

// Email method on the phone, dark mode: the code step of an email login.
const e = await ctx({ width: 390, height: 844, dark: true });
await login(e, 'editor@example.com');
await e.goto(BASE + '/backend/profile/two-factor');
await e.click('form[action$="/two-factor/email"] button[type=submit]');
await e.waitForLoadState('networkidle');
await e.goto(BASE + '/logout');
await e.click('form[action$="/logout"] button[type=submit]');
await login(e, 'editor@example.com');
await shot(e, '07-login-email-code-phone-dark');

// Group policy: the admin group requires 2FA.
execFileSync('php', ['-r', `$db=new SQLite3('${ROOT}/writable/database.db'); $db->exec("delete from settings where class='Config\\\\Auth' and key='twoFactorPolicy'"); $s=$db->prepare("insert into settings (class, key, value, type, context, created_at, updated_at) values ('Config\\\\Auth', 'twoFactorPolicy', :v, 'array', NULL, datetime('now'), datetime('now'))"); $s->bindValue(':v', serialize(['admin'=>'required'])); $s->execute();`]);
const a = await ctx();
await login(a, 'admin@example.com');
await a.goto(BASE + '/backend/profile');
await shot(a, '08-forced-setup', true);

await browser.close();
if (violations.length) { console.error('CSP violations:', violations); process.exit(1); }
