/**
 * Rich-text editor component: <textarea data-component="core/forms/editor" data-options='{…}'>.
 *
 * The vendored Quill 2 (BSD-3-Clause, public/assets/vendor/quill) is loaded on demand from the
 * URLs in the options ({"script": …, "style": …, "css": …}); it works under the enforced CSP
 * (no inline styles or scripts: it only uses the CSSOM). The textarea stays the form value: it is
 * hidden and kept in sync, so the form still posts `name`, and without JavaScript (or when the
 * editor fails to load) the textarea is used as is. The server sanitises the HTML with
 * HTMLPurifier in any case (Modules\Core\Security\HtmlSanitizer), which keeps alignment as the
 * classes text-center / text-right / text-justify this editor writes.
 */
import { t } from 'core/config.js';

const FORMATS = ['header', 'bold', 'italic', 'underline', 'strike', 'link', 'list', 'blockquote', 'code-block', 'align'];
const TOOLBAR = [
    [{ header: [2, 3, 4, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }, 'blockquote', 'code-block'],
    [{ align: [] }],
    ['link', 'clean'],
];
const LABELS = {
    'ql-header': 'editor.heading',
    'ql-bold': 'editor.bold',
    'ql-italic': 'editor.italic',
    'ql-underline': 'editor.underline',
    'ql-strike': 'editor.strike',
    'ql-list[value="ordered"]': 'editor.ordered',
    'ql-list[value="bullet"]': 'editor.bullet',
    'ql-blockquote': 'editor.quote',
    'ql-code-block': 'editor.code',
    'ql-align': 'editor.align',
    'ql-link': 'editor.link',
    'ql-clean': 'editor.clean',
};

const loading = new Map();

function load(tag, url) {
    if (!loading.has(url)) {
        loading.set(url, new Promise((resolve, reject) => {
            const element = document.createElement(tag);

            if (tag === 'link') {
                element.rel = 'stylesheet';
                element.href = url;
            } else {
                element.src = url;
                element.async = true;
            }

            element.addEventListener('load', () => resolve());
            element.addEventListener('error', () => reject(new Error('Cannot load ' + url)));
            document.head.appendChild(element);
        }));
    }

    return loading.get(url);
}

let registered = false;

/**
 * Alignment as classes text-center / text-right / text-justify instead of Quill's ql-align-*.
 */
function registerFormats(Quill) {
    if (registered) {
        return;
    }

    registered = true;
    // Quill's own registry (Quill.import), not an ES module import.
    const Parchment = Quill['import']('parchment');
    const Align = new Parchment.ClassAttributor('align', 'text', {
        scope: Parchment.Scope.BLOCK,
        whitelist: ['center', 'right', 'justify'],
    });
    Quill.register({ 'formats/align': Align, 'attributors/class/align': Align }, true);
}

function html(quill) {
    if (quill.getText().trim() === '' && quill.root.querySelector('img') === null) {
        return '';
    }

    // getSemanticHTML() writes real <ul>/<ol> and <pre>; it also turns spaces into &nbsp;.
    return quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
}

function label(toolbar) {
    for (const [selector, key] of Object.entries(LABELS)) {
        const [name, value] = selector.split('[');

        for (const control of toolbar.querySelectorAll('.' + name + (value ? '[' + value : ''))) {
            const text = t(key);
            control.setAttribute('aria-label', text);
            control.setAttribute('title', text);
        }
    }
}

export async function mount(textarea, options = {}) {
    if (!(textarea instanceof HTMLTextAreaElement) || !options.script || !options.style) {
        return;
    }

    try {
        await Promise.all([load('link', options.style), options.css ? load('link', options.css) : null, load('script', options.script)]);
    } catch (error) {
        console.warn(error);
        textarea.dataset.editor = 'plain';

        return;
    }

    const Quill = window.Quill;

    if (typeof Quill !== 'function') {
        textarea.dataset.editor = 'plain';

        return;
    }

    registerFormats(Quill);

    const wrapper = document.createElement('div');
    wrapper.className = 'kp-editor';
    const area = document.createElement('div');
    area.className = 'kp-editor-content';
    wrapper.appendChild(area);
    textarea.insertAdjacentElement('afterend', wrapper);
    textarea.classList.add('d-none');
    textarea.dataset.editor = 'quill';

    const quill = new Quill(area, {
        theme: 'snow',
        formats: FORMATS,
        modules: { toolbar: TOOLBAR },
        placeholder: textarea.getAttribute('placeholder') || '',
    });

    quill.clipboard.dangerouslyPasteHTML(textarea.value || '', 'silent');
    quill.root.setAttribute('role', 'textbox');
    quill.root.setAttribute('aria-multiline', 'true');
    quill.root.setAttribute('lang', textarea.getAttribute('lang') || document.documentElement.lang);

    const labelElement = textarea.id ? document.querySelector('label[for="' + CSS.escape(textarea.id) + '"]') : null;

    if (labelElement !== null) {
        labelElement.id = labelElement.id || textarea.id + '-label';
        quill.root.setAttribute('aria-labelledby', labelElement.id);
        // The label points at the hidden textarea: move the focus to the editor instead.
        labelElement.addEventListener('click', () => quill.focus());
    }

    const toolbar = wrapper.querySelector('.ql-toolbar');

    if (toolbar !== null) {
        label(toolbar);

        // Heading picker texts (Quill prints data-label from CSS).
        for (const item of toolbar.querySelectorAll('.ql-header .ql-picker-item, .ql-header .ql-picker-label')) {
            const level = item.getAttribute('data-value');
            item.setAttribute('data-label', level ? t('editor.heading' + level) : t('editor.normal'));
        }
    }

    // Texts of the link tooltip (Quill prints them from CSS; the editor CSS reads these attributes).
    const tooltip = wrapper.querySelector('.ql-tooltip');

    if (tooltip !== null) {
        tooltip.dataset.visit = t('editor.visit');
        tooltip.dataset.enter = t('editor.enterLink');
        tooltip.dataset.save = t('editor.save');
        tooltip.dataset.edit = t('editor.edit');
        tooltip.dataset.remove = t('editor.remove');
        const input = tooltip.querySelector('input[type="text"]');

        if (input !== null) {
            input.dataset.link = 'https://';
            input.setAttribute('aria-label', t('editor.link'));
        }
    }

    if (textarea.classList.contains('is-invalid')) {
        wrapper.classList.add('is-invalid');
    }

    let initial = html(quill);
    quill.on('text-change', () => {
        const value = html(quill);

        if (value !== initial) {
            initial = value;
            textarea.value = value;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    textarea.form?.addEventListener('submit', () => {
        textarea.value = html(quill);
    });

    return quill;
}
