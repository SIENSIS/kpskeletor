/**
 * Captcha reload button: `<div data-component="auth/captcha">` with an image
 * `[data-captcha-image]` and a button `[data-captcha-reload]`. A new image is a new challenge.
 */
export function mount(root) {
    const image = root.querySelector('[data-captcha-image]');
    const button = root.querySelector('[data-captcha-reload]');

    if (!(image instanceof HTMLImageElement) || !(button instanceof HTMLButtonElement)) {
        return;
    }

    const source = image.src.split('?')[0];

    button.addEventListener('click', () => {
        image.src = `${source}?r=${Date.now()}`;
        const input = root.querySelector('input[name="captcha"]');
        if (input instanceof HTMLInputElement) {
            input.value = '';
            input.focus();
        }
    });
}
