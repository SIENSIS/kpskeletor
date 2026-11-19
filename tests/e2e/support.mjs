// Shared checks of the end-to-end tests: CSP violations, horizontal overflow, axe (WCAG 2.1 AA)
// and a screenshot per project (writable/e2e/screenshots/<project>/<name>.png).
import { test as base, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

/** The axe rule sets checked: WCAG 2.0 and 2.1, levels A and AA. */
export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

export const test = base.extend({
    // Every page records the CSP violations the browser reports (except the development debug
    // toolbar's, `?debugbar`, which never runs in production).
    page: async ({ page, storageState }, use, testInfo) => {
        page.cspViolations = [];
        page.on('console', (message) => {
            if (/[?&]debugbar\b/.test(message.location().url ?? '')) return;
            if (/Content Security Policy|Refused to (execute|load|apply)/i.test(message.text())) {
                page.cspViolations.push(message.text());
            }
        });
        // The theme's colour switch follows the stored choice; dark projects store "dark".
        await page.addInitScript((scheme) => {
            try { localStorage.setItem('theme', scheme); } catch { /* storage blocked */ }
        }, testInfo.project.use.colorScheme ?? 'light');
        await use(page);

        // The application regenerates the session id now and then (and destroys the old one):
        // keep the refreshed cookie for the next test (one worker, tests run in order).
        if (typeof storageState === 'string' && !page.isClosed()) {
            const state = await page.context().storageState();
            if (state.cookies.length > 0) await page.context().storageState({ path: storageState });
        }
    },
});

export { expect };

/**
 * A back-office path under the site's prefix (app.backendPath; `E2E_BACKEND_PATH`, found by the
 * global set-up from where the login lands): backend('news') = '/admin/news'.
 */
export function backend(path = '') {
    const prefix = '/' + (process.env.E2E_BACKEND_PATH || 'backend');
    return path === '' ? prefix : `${prefix}/${path.replace(/^\/+/, '')}`;
}

/**
 * Opens a URL and checks it: status below 400, no CSP violation, no horizontal overflow, no axe
 * violation of AXE_TAGS; then saves a screenshot.
 */
export async function checkPage(page, url, name, options = {}) {
    const info = test.info();

    const response = await page.goto(url, { waitUntil: 'networkidle' });
    expect(response, `${url} answered`).not.toBeNull();
    expect(response.status(), `${url} status`).toBeLessThan(400);
    if (!/\/login(\/|\?|$)/.test(url)) {
        expect(page.url(), `${url} did not send to the login`).not.toMatch(/\/login(\?|$)/);
    }

    if (options.ready) await options.ready(page);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${url}: horizontal overflow at ${info.project.use.viewport.width}px`).toBeLessThanOrEqual(0);

    expect(page.cspViolations, `${url}: CSP violations`).toEqual([]);

    const violations = await axe(page, options.axeExclude ?? []);
    expect(violations, `${url}: axe violations\n${formatViolations(violations)}`).toEqual([]);

    await page.screenshot({ path: `writable/e2e/screenshots/${info.project.name}/${name}.png`, fullPage: false });
}

/**
 * Runs axe in the page (through the DevTools protocol, so the page's CSP is not weakened).
 *
 * @returns {Promise<Array<{id: string, impact: string, help: string, targets: string[]}>>}
 */
export async function axe(page, exclude = []) {
    if (!(await page.evaluate(() => Boolean(window.axe)))) {
        await page.evaluate(axeSource);
    }

    return page.evaluate(async ({ tags, exclude }) => {
        const context = exclude.length ? { exclude: exclude.map((s) => [s]) } : document;
        const result = await window.axe.run(context, { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations'] });

        return result.violations.map((v) => ({
            id: v.id,
            impact: v.impact,
            help: v.help,
            targets: v.nodes.slice(0, 5).map((n) => n.target.join(' ')),
        }));
    }, { tags: AXE_TAGS, exclude });
}

/**
 * The first Tab stop of a page is the visible "skip to content" link; Enter moves to #main.
 */
export async function checkSkipLink(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');

    const skip = page.locator('a.skip-link');
    await expect(skip).toBeFocused();
    await expect(skip).toBeVisible();

    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#main$/);
    expect(await page.evaluate(() => document.getElementById('main') !== null)).toBe(true);
}

function formatViolations(violations) {
    return violations.map((v) => `- ${v.id} (${v.impact}): ${v.help}\n    ${v.targets.join('\n    ')}`).join('\n');
}
