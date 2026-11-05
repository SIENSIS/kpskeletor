const { chromium } = require('playwright');
const fs = require('fs');
const base = 'http://localhost:8080';
const out = '/home/user/kpskeletor/deploy-status/10-kpapy/screenshots/';
const challenge = fs.readFileSync(process.argv[2], 'utf8').trim();
(async () => {
  const browser = await chromium.launch();
  const errors = [];
  async function ctx(opts = {}) {
    const c = await browser.newContext({ viewport: { width: opts.w || 1366, height: opts.h || 768 }, deviceScaleFactor: 2, colorScheme: opts.dark ? 'dark' : 'light' });
    const p = await c.newPage();
    p.on('console', m => { if (/Content.Security.Policy|Refused/i.test(m.text())) errors.push(m.text()); });
    return [c, p];
  }
  async function login(p, user) {
    await p.goto(base + '/login');
    await p.fill('input[name=email]', user);
    await p.fill('input[name=password]', 'Demo-kpskeletor-2026');
    await p.click('button[type=submit]');
    await p.waitForURL(/backend/);
  }
  async function shot(p, name, full = false) {
    const overflow = await p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    if (overflow) errors.push(name + ': horizontal scroll');
    if (await p.evaluate(() => document.documentElement.getAttribute('data-bs-theme') === null) && name.includes('dark')) {}
    await p.screenshot({ path: out + name + '.png', fullPage: full });
  }
  // 01/02: admin creates a personal token
  let [c, p] = await ctx();
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile/api-tokens');
  await p.fill('#api-token-name', 'Spreadsheet import');
  await p.check('#api-scope-some');
  await p.check('input[value="api.docs"]');
  await p.selectOption('#api-token-days', '90');
  await shot(p, '01-personal-tokens-form');
  await p.click('form[action$="api-tokens"] button[type=submit]');
  await p.waitForSelector('#api-new-token');
  await shot(p, '02-personal-token-created');
  // 03 admin list
  await p.goto(base + '/backend/api/tokens');
  await shot(p, '03-admin-token-list');
  // 04 docs
  await p.goto(base + '/backend/api/docs');
  await p.waitForTimeout(500);
  await shot(p, '04-api-docs');
  await c.close();
  // 05 dark + 06 phone
  [c, p] = await ctx({ dark: true });
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile/api-tokens');
  await p.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
  await shot(p, '05-personal-tokens-dark');
  await p.goto(base + '/backend/api/docs');
  await p.waitForTimeout(500);
  await shot(p, '06-api-docs-dark');
  await c.close();
  [c, p] = await ctx({ w: 390, h: 844 });
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile/api-tokens');
  await shot(p, '07-personal-tokens-phone');
  await p.goto(base + '/backend/api/tokens');
  await shot(p, '08-admin-token-list-phone');
  await c.close();
  // 09/10 deny page (guest)
  [c, p] = await ctx({ w: 390, h: 844 });
  await p.goto(base + '/auth/api-login/' + challenge);
  await shot(p, '09-deny-sign-in');
  await p.click('form button[type=submit]');
  await p.waitForLoadState();
  await shot(p, '10-sign-in-denied');
  await c.close();
  await browser.close();
  console.log(errors.length ? 'PROBLEMS:\n' + errors.join('\n') : 'OK');
})();
