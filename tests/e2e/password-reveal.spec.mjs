// The show/hide button of password fields (core/forms/password-reveal.js, docs/frontend.md
// "Password fields"): on the login page (a module view) and on a kpcrudy form (Users › new
// user), with the right accessible name and aria-pressed, the value sent normally, the field
// back to type="password" when the form is submitted, and `data-no-reveal` respected. axe runs
// on both pages in every project (light and dark).
import { test, expect, checkPage, backend } from './support.mjs';

const SHOW = 'Mostra la contrasenya';
const HIDE = 'Amaga la contrasenya';

async function checkToggle(page, field) {
    const input = page.locator(field);
    const id = await input.getAttribute('id');
    const button = page.locator(`button[aria-controls="${id}"]`);

    await expect(button).toHaveAttribute('type', 'button');
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    await expect(button).toHaveAccessibleName(SHOW);
    await expect(input).toHaveAttribute('type', 'password');

    await input.fill('Secret-123');
    await input.focus();
    await button.click();
    await expect(input).toHaveAttribute('type', 'text');
    await expect(button).toHaveAttribute('aria-pressed', 'true');
    await expect(button).toHaveAccessibleName(HIDE);
    await expect(input).toBeFocused();
    await expect(input).toHaveValue('Secret-123');

    // Keyboard: Tab to the button, Space toggles it back.
    await button.focus();
    await page.keyboard.press('Space');
    await expect(input).toHaveAttribute('type', 'password');
    await expect(button).toHaveAccessibleName(SHOW);

    return { input, button };
}

/**
 * Submits the form of a field with the value shown, stops the navigation and returns what was
 * sent and the field's type at that moment.
 */
async function submitShown(page, field, button) {
    await button.click();
    await expect(page.locator(field)).toHaveAttribute('type', 'text');

    const sent = page.evaluate((selector) => new Promise((resolve) => {
        const input = document.querySelector(selector);
        input.form.addEventListener('submit', (event) => {
            event.preventDefault();
            resolve({ type: input.type, value: new FormData(input.form).get(input.name) });
        }, { once: true });
    }), field);
    // The form's own submit button (requestSubmit fires the submit event like a click).
    // Other required fields stay empty: skip the browser's validation, the listener above
    // stops the submission anyway.
    await page.evaluate((selector) => {
        const form = document.querySelector(selector).form;
        form.noValidate = true;
        form.requestSubmit();
    }, field);

    return sent;
}

test.describe('as a guest', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login page', async ({ page }) => {
        await checkPage(page, '/login', 'password-reveal-login');
        const { button } = await checkToggle(page, '#password');

        // A field added later is enhanced; one with data-no-reveal is not.
        const added = await page.evaluate(() => {
            const form = document.querySelector('form');
            for (const [name, optOut] of [['later', false], ['skipped', true]]) {
                const input = document.createElement('input');
                input.type = 'password';
                input.name = name;
                input.id = name;
                input.className = 'form-control';
                if (optOut) input.dataset.noReveal = '';
                form.append(input);
            }
            return new Promise((resolve) => setTimeout(() => resolve({
                later: document.querySelector('button[aria-controls="later"]') !== null,
                skipped: document.querySelector('button[aria-controls="skipped"]') !== null,
            }), 50));
        });
        expect(added).toEqual({ later: true, skipped: false });

        const sent = await submitShown(page, '#password', button);
        expect(sent).toEqual({ type: 'password', value: 'Secret-123' });
    });
});

test('kpcrudy form: new user', async ({ page }) => {
    await checkPage(page, backend('users/add'), 'password-reveal-new-user');
    const field = 'form input[type="password"]';
    test.skip(await page.locator(field).count() === 0, 'The new user form has no password field.');

    const id = await page.locator(field).first().getAttribute('id');
    const { button } = await checkToggle(page, `#${id}`);
    const sent = await submitShown(page, `#${id}`, button);
    expect(sent).toEqual({ type: 'password', value: 'Secret-123' });
});

test('the field is hidden again on pagehide', async ({ page }) => {
    test.skip(test.info().project.name !== 'desktop-light', 'Once.');
    await page.goto(backend('users/add'));
    const id = await page.locator('form input[type="password"]').first().getAttribute('id');
    await page.locator(`button[aria-controls="${id}"]`).click();
    const type = await page.evaluate((fieldId) => {
        window.dispatchEvent(new PageTransitionEvent('pagehide'));
        return document.getElementById(fieldId).type;
    }, id);
    expect(type).toBe('password');
});
