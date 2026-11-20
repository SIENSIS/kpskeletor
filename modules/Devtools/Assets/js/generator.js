/**
 * The code generator's wizard (`data-component="devtools/generator"` on its form). Everything
 * works without it (every button posts the form and the server validates); it only adds live
 * help: inputs shown for the chosen column type and module mode, a warning for ON DELETE SET NULL
 * on a column that is not nullable, and the module prefix of table names.
 */
export function mount(form, options) {
    const texts = options || {};

    const toggleMode = () => {
        const mode = form.querySelector('[data-generator-mode]:checked');
        const value = mode ? mode.value : 'new';

        form.querySelectorAll('[data-generator-when]').forEach((element) => {
            element.hidden = element.dataset.generatorWhen !== value;
        });
    };

    const toggleType = (row) => {
        const select = row.querySelector('[data-generator-type]');
        const type = select ? select.value : 'string';

        row.querySelectorAll('[data-generator-for]').forEach((element) => {
            element.hidden = !element.dataset.generatorFor.split(' ').includes(type);
        });
    };

    const checkSetNull = (row) => {
        const rule = row.querySelector('[data-generator-ondelete]');
        const nullable = row.querySelector('input[type="checkbox"][data-generator-nullable]');
        const warning = row.querySelector('[data-generator-setnull-warning]');

        if (!rule || !nullable || !warning) {
            return;
        }

        const wrong = rule.value === 'SET NULL' && !nullable.checked && (row.querySelector('[name$="[foreign][table]"]') || {}).value;
        rule.classList.toggle('is-invalid', Boolean(wrong));
        warning.textContent = wrong ? String(texts.setNull || '') : '';
        warning.classList.toggle('d-block', Boolean(wrong));
    };

    const checkPrefix = (input) => {
        const name = form.querySelector('[data-generator-module-name]');
        const mode = form.querySelector('[data-generator-mode]:checked');
        const existing = form.querySelector('[name="module[existingName]"]');
        const module = mode && mode.value === 'existing' ? (existing ? existing.value : '') : (name ? name.value : '');
        const prefix = module.toLowerCase() + '_';
        const wrong = module !== '' && input.value !== '' && !input.value.startsWith(prefix);

        input.classList.toggle('is-invalid', wrong);
        input.setAttribute('aria-invalid', wrong ? 'true' : 'false');
        input.title = wrong ? String(texts.prefix || '').replace('…', prefix) : '';
    };

    form.querySelectorAll('[data-generator-column]').forEach((row) => {
        toggleType(row);
        checkSetNull(row);
        row.addEventListener('change', () => {
            toggleType(row);
            checkSetNull(row);
        });
    });

    form.querySelectorAll('[data-generator-mode]').forEach((radio) => radio.addEventListener('change', toggleMode));
    toggleMode();

    form.querySelectorAll('[data-generator-table-name]').forEach((input) => {
        input.addEventListener('input', () => checkPrefix(input));
    });
}
