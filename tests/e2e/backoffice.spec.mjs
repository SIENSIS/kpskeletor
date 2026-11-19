// The main back-office screens, as the administrator of the demo site: every screen at every
// width and colour mode without horizontal overflow, CSP violations or axe (WCAG 2.1 AA)
// violations. Screens that need a recent re-authentication (Settings › Security, Users'
// access, Backups' restore…) are left out: the e2e session never re-authenticates. The Tasks
// tutorial module is disabled by default: the e2e site enables it (docs/development.md).
import { test, expect, checkPage, checkSkipLink, backend } from './support.mjs';

const screens = [
    [backend(), 'dashboard'],
    [backend('news'), 'news-reader'],
    [backend('news/posts'), 'news-posts'],
    [backend('news/posts/add'), 'news-post-form'],
    [backend('pages'), 'pages'],
    [backend('files'), 'files'],
    [backend('calendar'), 'calendar'],
    [backend('events'), 'events'],
    [backend('tasks'), 'tasks-board'],
    [backend('tasks/tasks'), 'tasks-list'],
    [backend('users'), 'users'],
    [backend('users/groups'), 'groups'],
    [backend('users/permissions'), 'permission-matrix'],
    [backend('settings'), 'settings'],
    [backend('activity'), 'activity'],
    [backend('notifications'), 'notifications'],
    [backend('modules'), 'modules'],
    [backend('menus/public'), 'menus'],
    [backend('backups'), 'backups'],
    [backend('security'), 'security-status'],
    [backend('profile'), 'profile'],
];

for (const [url, name] of screens) {
    test(`back office ${url}`, async ({ page }) => {
        await checkPage(page, url, name);
    });
}

// Every entry of the generated menus (sidebar, settings, user) opens without overflow or
// CSP violations; the screens of the list above also get axe and a screenshot.
test('every menu entry opens', async ({ page }) => {
    test.skip(test.info().project.name !== 'desktop-light', 'Once, at desktop width.');
    test.setTimeout(240_000);

    await page.goto(backend());
    const origin = new URL(page.url()).origin;
    const links = await page.locator('a[href]').evaluateAll((anchors) => anchors.map((a) => a.href));
    const targets = [...new Set(links)]
        .filter((href) => href.startsWith(origin) && (new URL(href).pathname === backend() || new URL(href).pathname.startsWith(backend('') + '/')))
        .filter((href) => !/logout|\/export|download/.test(href))
        .map((href) => href.split('#')[0]);

    expect(targets.length).toBeGreaterThan(10);

    for (const href of [...new Set(targets)]) {
        const response = await page.goto(href, { waitUntil: 'networkidle' });
        // 2xx, or a redirect to the re-authentication form for sensitive screens.
        expect(response, href).not.toBeNull();
        expect(response.status(), href).toBeLessThan(400);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, `${href}: horizontal overflow`).toBeLessThanOrEqual(0);
    }
    expect(page.cspViolations).toEqual([]);
});


// Keyboard navigation: the first Tab stop is the "skip to content" link, and it moves to the
// main content past the menus.
test('keyboard: skip link', async ({ page }) => {
    await checkSkipLink(page, backend());
});
