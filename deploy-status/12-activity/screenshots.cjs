// Screenshots of session 12 (node deploy-status/12-activity/screenshots.cjs), against
// `php spark serve` with the Auth demo data. It first produces some real activity (a failed
// login, logins, a 403, a profile change, notification preferences) and then captures the
// Activity screens.
const { chromium } = require('playwright');
const base = 'http://localhost:8080';
const out = __dirname + '/screenshots/';
const password = 'Demo-kpskeletor-2026';
(async () => {
  const browser = await chromium.launch();
  const errors = [];
  async function ctx(opts = {}) {
    const c = await browser.newContext({ viewport: { width: opts.w || 1366, height: opts.h || 768 }, deviceScaleFactor: 2, colorScheme: opts.dark ? 'dark' : 'light' });
    const p = await c.newPage();
    p.on('console', m => { if (/Content.Security.Policy|Refused/i.test(m.text())) errors.push(m.text()); });
    p.on('pageerror', e => errors.push(String(e)));
    return [c, p];
  }
  async function login(p, user, pass = password, expectOk = true) {
    await p.goto(base + '/login');
    await p.fill('input[name=email]', user);
    await p.fill('input[name=password]', pass);
    await p.click('button[type=submit]');
    if (expectOk) await p.waitForURL(/backend/); else await p.waitForLoadState();
  }
  async function shot(p, name, full = false) {
    const overflow = await p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    if (overflow) errors.push(name + ': horizontal scroll');
    await p.screenshot({ path: out + name + '.png', fullPage: full });
  }

  // Some activity first.
  let [c, p] = await ctx();
  await login(p, 'usuari@example.com', 'not-the-password', false);
  await login(p, 'usuari@example.com');
  await p.goto(base + '/backend/activity');
  await p.goto(base + '/backend/profile');
  await p.fill('input[name=name]', 'Usuària de prova');
  await p.selectOption('select[name=theme_mode]', 'dark');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="backend/profile"] button[type=submit]')]);
  await c.close();

  [c, p] = await ctx();
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile/notifications');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="backend/profile/notifications"] button[type=submit]')]);
  await p.goto(base + '/backend/activity');
  await shot(p, '01-activity-log');
  await p.goto(base + '/backend/activity?f[type]=security');
  await shot(p, '02-activity-log-security-filter');
  await p.goto(base + '/backend/activity');
  const profileRow = p.locator('tr', { hasText: /Perfil actualitzat|Profile updated/ }).first();
  await profileRow.locator('a[href*="/backend/activity/show/"]').first().click();
  await p.waitForLoadState();
  await shot(p, '03-entry-details', true);
  await c.close();

  [c, p] = await ctx();
  await login(p, 'usuari@example.com');
  await p.goto(base + '/backend/activity/mine');
  await shot(p, '04-my-activity');
  await c.close();

  [c, p] = await ctx({ w: 390, h: 844 });
  await login(p, 'usuari@example.com');
  await p.goto(base + '/backend/activity/mine');
  await shot(p, '05-my-activity-phone', true);
  await c.close();

  [c, p] = await ctx({ dark: true });
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/activity');
  await shot(p, '06-activity-log-dark');
  await c.close();

  await browser.close();
  if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
  console.log('Screenshots written to ' + out);
})();
