/**
 * Target fields of the menu item and custom link forms (`data-component="menus/link-form"`):
 * shows only the field of the chosen type (the URL/route field, or the choices of a module's
 * link type). Without JavaScript every field is shown and the server reads the right one.
 */
export function mount(form) {
    const type = form.querySelector('[data-link-type]');

    if (!(type instanceof HTMLSelectElement)) {
        return;
    }

    const update = () => {
        form.querySelectorAll('[data-link-field]').forEach((field) => {
            const types = (field.dataset.linkField || '').split(' ');
            field.classList.toggle('d-none', !types.includes(type.value));
        });
    };

    type.addEventListener('change', update);
    update();
}
