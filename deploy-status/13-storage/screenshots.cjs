// Screenshots of session 13 (node deploy-status/13-storage/screenshots.cjs <photo key> <csv key>),
// against `php spark serve` with the Auth demo data and two private files uploaded by
// admin@example.com (see the README). Prints the response headers of each private file request.
const { chromium } = require('playwright');
const base = 'http://localhost:8080';
const out = __dirname + '/screenshots/';
const password = 'Demo-kpskeletor-2026';
const [photo, csv] = process.argv.slice(2);
(async () => {
  const browser = await chromium.launch();
  const errors = [];
  async function ctx(opts = {}) {
    const c = await browser.newContext({ viewport: { width: opts.w || 1366, height: opts.h || 768 }, deviceScaleFactor: 2, colorScheme: opts.dark ? 'dark' : 'light' });
    const p = await c.newPage();
    p.on('pageerror', e => errors.push(String(e)));
    return [c, p];
  }
  async function login(p, user) {
    await p.goto(base + '/login');
    await p.fill('input[name=email]', user);
    await p.fill('input[name=password]', password);
    await p.click('button[type=submit]');
    await p.waitForURL(/backend/);
  }
  function headers(label, response) {
    const h = response.headers();
    console.log(`\n${label}: HTTP ${response.status()}`);
    for (const name of ['content-type', 'content-disposition', 'x-content-type-options', 'content-security-policy', 'x-frame-options', 'cross-origin-resource-policy', 'cache-control']) {
      if (h[name]) console.log(`  ${name}: ${h[name]}`);
    }
  }

  // The owner sees the photo inline.
  let [c, p] = await ctx();
  await login(p, 'admin@example.com');
  headers('admin@example.com GET files/private/' + photo, await p.goto(base + '/files/private/' + photo));
  await p.screenshot({ path: out + '01-owner-sees-private-photo.png' });
  headers('admin@example.com GET files/private/' + csv, await p.request.get(base + '/files/private/' + csv));
  headers('admin@example.com GET files/private/' + photo + '?download=1', await p.request.get(base + '/files/private/' + photo + '?download=1'));
  await c.close();

  // Another user and a guest get the 404 page.
  [c, p] = await ctx();
  await login(p, 'usuari@example.com');
  headers('usuari@example.com GET files/private/' + photo, await p.goto(base + '/files/private/' + photo));
  await p.screenshot({ path: out + '02-other-user-gets-404.png' });
  await c.close();
  [c, p] = await ctx({ w: 390, h: 844, dark: true });
  headers('guest GET files/private/' + photo, await p.goto(base + '/files/private/' + photo));
  await p.screenshot({ path: out + '03-guest-gets-404-phone-dark.png' });
  await c.close();

  // Superadmin, and the refused uploads in the security log.
  [c, p] = await ctx();
  await login(p, 'superadmin@example.com');
  headers('superadmin@example.com GET files/private/' + photo, await p.request.get(base + '/files/private/' + photo));
  await p.goto(base + '/backend/activity');
  await p.screenshot({ path: out + '04-refused-uploads-in-activity-log.png' });
  await c.close();

  await browser.close();
  if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
})();
