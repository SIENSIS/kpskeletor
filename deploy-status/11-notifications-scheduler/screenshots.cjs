// Screenshots of session 11 (node deploy-status/11-notifications-scheduler/screenshots.cjs),
// against `php spark serve` with the Auth demo data and a few notifications for admin@example.com.
const { chromium } = require('playwright');
const base = 'http://localhost:8080';
const out = __dirname + '/screenshots/';
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
    const offscreen = await p.evaluate(() => [...document.querySelectorAll('.dropdown-menu.show')].some(m => { const r = m.getBoundingClientRect(); return r.left < 0 || r.right > window.innerWidth; }));
    if (offscreen) errors.push(name + ': an open menu leaves the screen');
    await p.screenshot({ path: out + name + '.png', fullPage: full });
  }
  async function openBell(p) {
    await p.waitForLoadState('networkidle');
    await p.click('[data-bell-button]');
    await p.waitForSelector('[data-bell-list] a');
    await p.waitForTimeout(300);
  }
  let [c, p] = await ctx();
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile');
  await openBell(p);
  await shot(p, '01-bell');
  await p.goto(base + '/backend/notifications');
  await shot(p, '02-notifications-list');
  await p.goto(base + '/backend/profile/notifications');
  await shot(p, '03-notification-preferences', true);
  await p.goto(base + '/backend/schedule');
  await shot(p, '04-scheduled-tasks');
  await p.click('a[href*="/backend/schedule/show/"]');
  await p.waitForLoadState();
  await shot(p, '05-scheduled-task-runs', true);
  await c.close();

  [c, p] = await ctx({ dark: true });
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/schedule');
  await openBell(p);
  await shot(p, '06-bell-dark');
  await c.close();

  [c, p] = await ctx({ w: 390, h: 844 });
  await login(p, 'admin@example.com');
  await p.goto(base + '/backend/profile');
  await openBell(p);
  await shot(p, '07-bell-phone');
  await p.goto(base + '/backend/notifications');
  await shot(p, '08-notifications-phone', true);
  await p.goto(base + '/backend/schedule');
  await shot(p, '09-scheduled-tasks-phone', true);
  await c.close();

  [c, p] = await ctx();
  await login(p, 'usuari@example.com');
  await p.goto(base + '/backend/profile/notifications');
  await shot(p, '10-preferences-user');
  const status = (await p.goto(base + '/backend/schedule')).status();
  if (status !== 403) errors.push('usuari@ should get 403 on backend/schedule, got ' + status);
  await c.close();

  await browser.close();
  if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
  console.log('ok');
})();
