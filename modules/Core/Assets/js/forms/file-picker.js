/**
 * File picker component: <div data-component="core/forms/file-picker" data-options='{…}'>
 * rendered by Modules\Core\Storage\Picker\PickerField::render() (docs/files.md, "File picker").
 *
 * The hidden input [data-picker-value] is the form value: the keys of the chosen files, separated
 * by commas. "Upload" sends each file with the field's signed token to `files/picker/upload`
 * (one request per file, native <progress>); "Choose" opens a dialog that lists what the token
 * allows (`files/picker/browse`). The server checks everything again when the form is saved.
 *
 * Also exported for other components (the rich-text editor's "Insert image"):
 *   uploadFile(options, file, progress) → Promise<item>
 *   pickDialog(options, { alt: true }) → Promise<{ item, alt } | null>
 */
import { t } from 'core/config.js';
import { get, refreshCsrf } from 'core/http.js';
import { openModal } from 'core/ui/modal.js';

function el(tag, attributes = {}, ...children) {
    const element = document.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
        if (value === false || value === null || value === undefined) {
            continue;
        }

        if (name === 'class') {
            element.className = value;
        } else if (name.startsWith('on') && typeof value === 'function') {
            element.addEventListener(name.slice(2), value);
        } else {
            element.setAttribute(name, value === true ? '' : value);
        }
    }

    for (const child of children) {
        if (child !== null && child !== undefined && child !== false) {
            element.append(child instanceof Node ? child : document.createTextNode(String(child)));
        }
    }

    return element;
}

function icon(name) {
    return el('i', { class: 'bi ' + name, 'aria-hidden': 'true' });
}

export function loadStyle(options) {
    if (options.css && !document.querySelector('link[data-file-picker-css]')) {
        document.head.append(el('link', { rel: 'stylesheet', href: options.css, 'data-file-picker-css': true }));
    }
}

function csrf() {
    const header = document.querySelector('meta[name="csrf-header"]');
    const token = document.querySelector('meta[name="csrf-token"]');

    return header && token ? { header: header.content, token: token.content, element: token } : null;
}

function extension(name) {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

/**
 * Checks a file against the field's types and size before sending it (the server checks again).
 * Returns an error message or null.
 */
export function precheck(options, file) {
    const types = Array.isArray(options.types) ? options.types : [];

    if (types.length > 0 && !types.includes(extension(file.name))) {
        return t('picker.type', { name: file.name });
    }

    if (options.maxSize > 0 && file.size > options.maxSize) {
        return t('picker.tooLarge', { name: file.name, max: Math.round((options.maxSize / 1048576) * 10) / 10 });
    }

    return null;
}

/**
 * Uploads one file with the field's token. Resolves with the stored file (key, name, url…),
 * rejects with an Error whose message can be shown.
 */
export function uploadFile(options, file, progress = null) {
    return new Promise((resolve, reject) => {
        const problem = precheck(options, file);

        if (problem !== null) {
            reject(new Error(problem));

            return;
        }

        const body = new FormData();
        body.append('token', options.token);
        body.append('file', file, file.name);
        const request = new XMLHttpRequest();
        request.open('POST', options.upload);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        const token = csrf();

        if (token) {
            request.setRequestHeader(token.header, token.token);
        }

        if (progress) {
            progress.hidden = false;
            progress.value = 0;
            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    progress.value = Math.round((event.loaded / event.total) * 100);
                }
            });
        }

        request.addEventListener('load', () => {
            if (token) {
                refreshCsrf(request.getResponseHeader(token.header));
            }

            if (progress) {
                progress.hidden = true;
            }

            let data = null;

            try {
                data = JSON.parse(request.responseText);
            } catch (error) {
                data = null;
            }

            if (request.status === 201 && data && data.file) {
                resolve(data.file);
            } else {
                reject(new Error((data && data.message) || t('http.error')));
            }
        });
        request.addEventListener('error', () => {
            if (progress) {
                progress.hidden = true;
            }

            reject(new Error(t('http.network')));
        });
        request.send(body);
    });
}

function canUpload(options) {
    return options.mode === 'upload' || options.mode === 'both';
}

function canBrowse(options) {
    return options.mode === 'browse' || options.mode === 'both';
}

function preview(item) {
    return item.image
        ? el('img', { src: item.thumbnail || item.url, alt: '', loading: 'lazy' })
        : el('span', { class: 'file-picker-icon d-flex align-items-center justify-content-center' }, icon('bi-file-earmark'));
}

/**
 * The dialog to choose a file (and, for images in rich text, upload one and give its alt text).
 * Resolves with { item, alt } or null.
 */
export async function pickDialog(options, settings = {}) {
    loadStyle(options);
    let selected = null;
    const status = el('p', { class: 'small text-body-secondary mb-2', 'aria-live': 'polite' });
    const grid = el('div', { class: 'file-picker-grid', role: 'list' });
    const heading = el('div', { class: 'd-flex align-items-center gap-2 mb-2 flex-wrap' });
    const body = el('div', {});
    const progress = el('progress', { class: 'file-picker-progress', max: '100', value: '0', hidden: true });
    let altInput = null;

    function select(item, button) {
        selected = item;

        for (const other of grid.querySelectorAll('[aria-pressed]')) {
            other.setAttribute('aria-pressed', 'false');
        }

        if (button) {
            button.setAttribute('aria-pressed', 'true');
        }

        if (altInput !== null && altInput.value === '') {
            altInput.focus();
        }
    }

    function choice(item) {
        const button = el('button', {
            type: 'button',
            class: 'btn btn-outline-secondary file-picker-choice',
            'aria-pressed': 'false',
            role: 'listitem',
            title: item.name,
            onclick: () => select(item, button),
        }, preview(item), el('span', { class: 'file-picker-name' }, item.name));

        return button;
    }

    async function open(path = '') {
        status.textContent = t('picker.loading');
        grid.replaceChildren();
        heading.replaceChildren();
        let data;

        try {
            const query = new URLSearchParams({ token: options.token, path });
            data = await get(options.browse + '?' + query.toString(), { silent: true });
        } catch (error) {
            status.textContent = error.message;

            return;
        }

        if (Array.isArray(data.roots) && data.roots.length > 1) {
            const roots = el('select', { class: 'form-select form-select-sm w-auto', 'aria-label': t('picker.title'), onchange: (event) => open(event.target.value) });

            for (const root of data.roots) {
                const option = el('option', { value: root.path }, root.name);
                option.selected = data.path === root.path || data.path.startsWith(root.path + '/');
                roots.append(option);
            }

            heading.append(roots);
        }

        if (data.parent) {
            heading.append(el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: () => open(data.parent) }, icon('bi-arrow-up me-1'), t('picker.up')));
        }

        heading.append(el('code', { class: 'small' }, data.path));

        for (const folder of data.folders || []) {
            grid.append(el('button', { type: 'button', class: 'btn btn-outline-secondary file-picker-choice', role: 'listitem', onclick: () => open(folder.path) },
                el('span', { class: 'file-picker-icon d-flex align-items-center justify-content-center' }, icon('bi-folder-fill text-warning')),
                el('span', { class: 'file-picker-name' }, folder.name)));
        }

        for (const item of data.files || []) {
            grid.append(choice(item));
        }

        status.textContent = (data.files || []).length === 0 ? t('picker.empty') : '';
    }

    if (settings.alt && canUpload(options)) {
        const input = el('input', { type: 'file', class: 'form-control form-control-sm', accept: options.accept || false, 'aria-label': t('picker.upload') });
        input.addEventListener('change', async () => {
            const file = input.files && input.files[0];

            if (!file) {
                return;
            }

            status.textContent = t('picker.uploading', { name: file.name });

            try {
                const item = await uploadFile(options, file, progress);
                const button = choice(item);
                grid.prepend(button);
                select(item, button);
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message;
            }

            input.value = '';
        });
        body.append(el('div', { class: 'mb-3' }, el('label', { class: 'form-label small mb-1' }, t('picker.upload')), input, progress));
    }

    body.append(heading, status, grid);

    if (settings.alt) {
        const id = 'picker-alt-' + Math.random().toString(36).slice(2);
        altInput = el('input', { type: 'text', id, class: 'form-control', maxlength: '250', 'aria-describedby': id + '-help' });
        body.append(el('div', { class: 'mt-3' },
            el('label', { class: 'form-label', for: id }, t('picker.alt')),
            altInput,
            el('div', { class: 'form-text', id: id + '-help' }, t('picker.altHelp'))));
    }

    if (canBrowse(options)) {
        open('');
    } else {
        status.textContent = '';
    }

    const result = await openModal({
        title: t(settings.alt ? 'editor.image' : 'picker.title'),
        body,
        size: 'lg',
        buttons: [
            { label: t('picker.cancel'), value: null, variant: 'secondary' },
            { label: t(settings.alt ? 'picker.insert' : 'picker.choose'), value: 'ok', variant: 'primary' },
        ],
    });

    if (result !== 'ok' || selected === null) {
        return null;
    }

    return { item: selected, alt: altInput ? altInput.value.trim() : '' };
}

export function mount(element, options = {}) {
    if (!options.token) {
        return;
    }

    loadStyle(options);
    const value = element.querySelector('[data-picker-value]');
    const list = element.querySelector('[data-picker-items]');
    const input = element.querySelector('[data-picker-input]');
    const uploadLabel = element.querySelector('[data-picker-upload]');
    const browseButton = element.querySelector('[data-picker-browse]');
    const progress = element.querySelector('[data-picker-progress]');
    const error = element.querySelector('[data-picker-error]');
    const maxFiles = options.maxFiles > 0 ? options.maxFiles : 1;

    if (value === null || list === null) {
        return;
    }

    element.dataset.filePicker = 'ready';

    function showError(message) {
        if (error !== null) {
            error.textContent = message || '';
            error.hidden = !message;
        }

        element.classList.toggle('is-invalid', Boolean(message));
    }

    function keys() {
        return [...list.querySelectorAll('[data-key]')].map((item) => item.dataset.key);
    }

    function sync() {
        value.value = keys().join(',');
        value.dispatchEvent(new Event('input', { bubbles: true }));
        value.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function render(item) {
        return el('li', { class: 'file-picker-item card', 'data-key': item.key },
            item.image
                ? el('img', { class: 'file-picker-thumb card-img-top', src: item.thumbnail || item.url, alt: '' })
                : el('span', { class: 'file-picker-icon card-img-top d-flex align-items-center justify-content-center' }, icon('bi-file-earmark')),
            el('span', { class: 'file-picker-name small text-truncate px-2 pt-1' }, item.name),
            el('button', { type: 'button', class: 'btn btn-sm btn-link text-danger file-picker-remove', 'data-picker-remove': true, 'aria-label': t('picker.remove', { name: item.name }) }, icon('bi-x-lg')));
    }

    function add(item) {
        if (keys().includes(item.key)) {
            return;
        }

        if (maxFiles === 1) {
            list.replaceChildren();
        }

        list.append(render(item));
        sync();
    }

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-picker-remove]');

        if (button !== null) {
            button.closest('[data-key]')?.remove();
            showError('');
            sync();
        }
    });

    if (input !== null && uploadLabel !== null && canUpload(options)) {
        uploadLabel.hidden = false;
        input.addEventListener('change', async () => {
            const files = [...(input.files || [])];
            input.value = '';
            showError('');

            if (maxFiles > 1 && keys().length + files.length > maxFiles) {
                showError(t('picker.tooMany', { max: maxFiles }));

                return;
            }

            for (const file of files.slice(0, maxFiles)) {
                try {
                    add(await uploadFile(options, file, progress));
                } catch (problem) {
                    showError(problem.message);
                }
            }
        });
    }

    if (browseButton !== null && canBrowse(options)) {
        browseButton.hidden = false;
        browseButton.addEventListener('click', async () => {
            showError('');

            if (maxFiles > 1 && keys().length >= maxFiles) {
                showError(t('picker.tooMany', { max: maxFiles }));

                return;
            }

            const result = await pickDialog(options);

            if (result !== null) {
                add(result.item);
            }

            browseButton.focus();
        });
    }
}
