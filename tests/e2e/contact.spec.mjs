// The Contact module (session 48, D-48-01, D-48-02): the contact page and a page with the
// `contact.form` section at every width and colour mode (no CSP violation, no overflow, axe);
// a message with a wrong captcha is refused with the field error and its values kept (desktop
// light only: it counts against the forms rate limit); the back-office screens of messages and
// recipients as the administrator.
import { test, expect, checkPage, axe, backend } from './support.mjs';

test.describe('public', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('the contact page', async ({ page }) => {
        const response = await page.request.get('/contact');
        test.skip(response.status() === 404, 'Contact module disabled.');

        await checkPage(page, '/contact', 'contact-page');
        const form = page.locator('form.kp-contact-form');
        await expect(form).toBeVisible();
        // The honeypot: visually hidden, hidden from screen readers, out of the tab order.
        await expect(form.locator('.visually-hidden[aria-hidden="true"] input[name="website"][tabindex="-1"]')).toHaveCount(1);
        await expect(form.locator('img[data-captcha-image]')).toBeVisible();
        expect(await page.content()).not.toContain('@example.com');
        await expect(page.locator('main h1')).toHaveCount(1);
    });

    test('a page with the contact form section', async ({ page }) => {
        const response = await page.request.get('/escriu-nos');
        test.skip(response.status() === 404, 'No Pages demo page "Escriu-nos".');

        await checkPage(page, '/escriu-nos', 'contact-section');
        await expect(page.locator('form.kp-contact-form')).toBeVisible();
        await expect(page.locator('main h1')).toHaveCount(1);
    });

    test('a wrong captcha is refused and the values are kept', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop-light', 'Posts the form: once, on desktop light.');
        const response = await page.request.get('/contact');
        test.skip(response.status() === 404, 'Contact module disabled.');

        await page.goto('/contact');
        const form = page.locator('form.kp-contact-form');
        await form.locator('input[name="name"]').fill('Prova E2E');
        await form.locator('input[name="email"]').fill('prova@example.org');
        await form.locator('select[name="recipient"]').selectOption({ index: 1 });
        await form.locator('input[name="subject"]').fill('Prova del formulari');
        await form.locator('textarea[name="message"]').fill('Un missatge de prova escrit pel test e2e.');
        await form.locator('input[name="captcha"]').fill('999');
        await form.locator('input[name="privacy"]').check();
        await page.waitForTimeout(3500); // the time trap refuses forms sent in less than 3 s
        await form.locator('button[type="submit"]').click();
        await page.waitForLoadState('networkidle');

        await expect(page.locator('#contact-captcha')).toHaveClass(/is-invalid/);
        await expect(page.locator('input[name="subject"]')).toHaveValue('Prova del formulari');
        expect(page.cspViolations).toEqual([]);
        expect(await axe(page)).toEqual([]);
        await page.screenshot({ path: `writable/e2e/screenshots/${testInfo.project.name}/contact-refused.png`, fullPage: true });
    });
});

test.describe('back office', () => {
    test('messages and recipients', async ({ page }) => {
        const response = await page.request.get(backend('contact/messages'));
        test.skip(response.status() === 404, 'Contact module disabled.');

        await checkPage(page, backend('contact/messages'), 'contact-messages');
        const show = page.locator('a[href*="/contact/messages/show/"]').first();
        if (await show.count() > 0) {
            await checkPage(page, await show.getAttribute('href'), 'contact-message');
        }
        await checkPage(page, backend('contact/recipients'), 'contact-recipients');
    });
});
