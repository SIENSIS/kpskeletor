import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
import { execSync } from 'node:child_process';
const S = process.env.PACKAGES_DIR; // folder with pkg/Glossary-1.0.0.zip, pkg/Glossary-1.1.0.zip, pkg/Evil-slip.zip
const OUT = '/home/user/kpskeletor/deploy-status/29-module-packages/screenshots';
const BASE = 'http://localhost:8080';
const browser = await chromium.launch();
const violations = [];
async function ctx(opts = {}) {
  const c = await browser.newContext({ deviceScaleFactor: 2, viewport: { width: opts.width ?? 1280, height: opts.height ?? 900 }, colorScheme: opts.dark ? 'dark' : 'light' });
  return c;
}
async function login(c) {
  const p = await c.newPage();
  p.on('console', m => { if (/Content.Security.Policy|Refused to/i.test(m.text())) violations.push(m.text()); });
  await p.goto(BASE + '/login');
  await p.fill('input[name=email]', 'admin@example.com');
  await p.fill('input[name=password]', process.env.ADMIN_PASSWORD);
  await p.click('button[type=submit]');
  await p.waitForURL(/backend/);
  return p;
}
async function shot(p, name, full = true) {
  const overflow = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  if (overflow) console.log('HORIZONTAL OVERFLOW in', name);
  await p.screenshot({ path: `${OUT}/${name}.png`, fullPage: full });
  console.log('saved', name);
}
const accept = p => p.on('dialog', d => d.accept());
const c = await ctx();
const p = await login(c);
// confirm dialogs of data-confirm use a Bootstrap modal: click the modal's confirm button if shown
async function confirmClick(selector) {
  await p.click(selector);
  const ok = p.locator('.modal.show .modal-footer button').last();
  await ok.waitFor({ timeout: 5000 });
  await Promise.all([p.waitForNavigation(), ok.click()]);
  await p.waitForLoadState('networkidle');
}
accept(p);

// 1. Upload a refused package
await p.goto(BASE + '/backend/modules/packages/upload');
await p.setInputFiles('#package-file', `${S}/pkg/Evil-slip.zip`);
await p.click('form[action$="/packages/upload"] button[type=submit]');
await p.waitForLoadState('networkidle');
await shot(p, '05-upload-refused');

// 2. Upload Glossary 1.0.0 → details → approve
await p.goto(BASE + '/backend/modules/packages/upload');
await p.setInputFiles('#package-file', `${S}/pkg/Glossary-1.0.0.zip`);
await p.click('form[action$="/packages/upload"] button[type=submit]');
await p.waitForURL(/packages\/\d+$/);
await shot(p, '04-package-details');
await confirmClick('form[action$="/approve"] button');
// 3. Console runs the pending operation
execSync(`cd /home/user/kpskeletor && php spark modules:install --pending > ${S}/out-pending.txt 2>&1`);
// 4. Upload 1.1.0 (waiting for approval) and request an uninstall of Glossary
await p.goto(BASE + '/backend/modules/packages/upload');
await p.setInputFiles('#package-file', `${S}/pkg/Glossary-1.1.0.zip`);
await p.click('form[action$="/packages/upload"] button[type=submit]');
await p.waitForURL(/packages\/\d+$/);
await p.goto(BASE + '/backend/modules/uninstall/Pages');
await shot(p, '06-uninstall-request');
await p.goto(BASE + '/backend/modules/packages');
await shot(p, '03-packages');

// 5. Modules list
await p.goto(BASE + '/backend/modules');
await shot(p, '01-modules');
// 6. Enable with dependencies: disable Updates then Backup, enable Updates
await confirmClick('form[action$="/disable/Updates"] button');
await confirmClick('form[action$="/disable/Backup"] button');
await confirmClick('form[action$="/enable/Updates"] button');
await shot(p, '02-enable-dependencies', false);
await Promise.all([p.waitForNavigation(), p.click('[data-enable-dependencies] button[type=submit]')]);
// 7. Themes
await p.goto(BASE + '/backend/modules/themes');
await shot(p, '07-themes');
// 8. Phone + dark
const c2 = await ctx({ width: 390, height: 844, dark: true });
const p2 = await login(c2);
await p2.goto(BASE + '/backend/modules');
await shot(p2, '08-modules-phone-dark');
await p2.goto(BASE + '/backend/modules/packages');
await shot(p2, '09-packages-phone-dark');
console.log('CSP violations:', violations.length, violations.slice(0, 3));
await browser.close();
