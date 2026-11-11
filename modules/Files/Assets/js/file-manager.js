/**
 * File manager (`data-component="files/file-manager"`, modules/Files/Views/manager.php).
 *
 * Everything comes from the JSON endpoints under `backend/files/api` (the server checks the RBAC
 * permission and the folder ACL of every request; the buttons shown here only follow the rights
 * it reports):
 *
 * - folder tree (spaces: personal, shared public/private, other users and module folders for
 *   `files.all`), breadcrumbs, list and grid views (remembered per browser), search and filters;
 * - upload: button or drag and drop, several files, one request per file with a `<progress>` bar
 *   (no inline styles: strict CSP);
 * - details panel: preview, metadata form (name, alt text, tags), download, copy URL, move,
 *   trash; the trash view restores or deletes for good;
 * - folders: create, rename, delete (empty only) and the permissions editor (inherit or own
 *   entries for groups, users and all logged-in users; quota for `files.all`).
 */
import { post, get } from 'core/http.js';
import { toast } from 'core/ui/toast.js';
import { confirm } from 'core/ui/confirm.js';
import { openModal } from 'core/ui/modal.js';

const VIEW_KEY = 'files.view';

function el(tag, attributes = {}, ...children) {
    const element = document.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
        if (value === null || value === undefined || value === false) {
            continue;
        }

        if (name === 'class') {
            element.className = value;
        } else if (name === 'text') {
            element.textContent = value;
        } else if (name.startsWith('on') && typeof value === 'function') {
            element.addEventListener(name.slice(2), value);
        } else {
            element.setAttribute(name, value === true ? '' : String(value));
        }
    }

    for (const child of children.flat()) {
        if (child !== null && child !== undefined && child !== false) {
            element.append(child instanceof Node ? child : document.createTextNode(String(child)));
        }
    }

    return element;
}

function icon(name) {
    return el('i', { class: 'bi ' + name, 'aria-hidden': 'true' });
}

function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = Number(bytes) || 0;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return (unit === 0 ? value : value.toFixed(1)) + ' ' + units[unit];
}

function fileIcon(file) {
    if (file.image) {
        return 'bi-file-earmark-image';
    }

    const map = { pdf: 'bi-file-earmark-pdf', doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word', odt: 'bi-file-earmark-word', xls: 'bi-file-earmark-spreadsheet', xlsx: 'bi-file-earmark-spreadsheet', ods: 'bi-file-earmark-spreadsheet', csv: 'bi-file-earmark-spreadsheet', ppt: 'bi-file-earmark-slides', pptx: 'bi-file-earmark-slides', odp: 'bi-file-earmark-slides', txt: 'bi-file-earmark-text', zip: 'bi-file-earmark-zip' };

    return map[file.extension] || 'bi-file-earmark';
}

function readView() {
    try {
        return window.localStorage.getItem(VIEW_KEY) === 'grid' ? 'grid' : 'list';
    } catch (error) {
        return 'list';
    }
}

function storeView(view) {
    try {
        window.localStorage.setItem(VIEW_KEY, view);
    } catch (error) {
        // per-browser convenience only
    }
}

export function mount(root, options = {}) {
    const api = String(options.api || '').replace(/\/$/, '');
    const texts = options.texts || {};
    const tx = (key, ...args) => {
        let text = String(texts[key] ?? key);
        args.forEach((value, index) => {
            text = text.replace('{' + index + '}', String(value));
        });

        return text;
    };

    if (options.css && !document.querySelector('link[data-fm-css]')) {
        document.head.append(el('link', { rel: 'stylesheet', href: options.css, 'data-fm-css': true }));
    }

    const treeList = root.querySelector('[data-fm-tree]');
    const treeNav = root.querySelector('.fm-tree');
    const listing = root.querySelector('[data-fm-listing]');
    const crumbs = root.querySelector('[data-fm-crumbs]');
    const quota = root.querySelector('[data-fm-quota]');
    const details = root.querySelector('[data-fm-details]');
    const queue = root.querySelector('[data-fm-queue]');
    const filters = root.querySelector('[data-fm-filters]');
    const drop = root.querySelector('[data-fm-drop]');
    const dropzone = root.querySelector('[data-fm-dropzone]');
    const input = root.querySelector('[data-fm-input]');
    const buttons = {
        upload: root.querySelector('[data-fm-upload]'),
        newFolder: root.querySelector('[data-fm-new-folder]'),
        renameFolder: root.querySelector('[data-fm-rename-folder]'),
        deleteFolder: root.querySelector('[data-fm-delete-folder]'),
        acl: root.querySelector('[data-fm-acl]'),
    };

    const state = { tree: [], current: null, contents: null, view: readView(), trash: false, selected: null };

    const can = (right) => Boolean(state.contents && state.contents.rights.includes(right));
    const url = (path, params = {}) => {
        const query = new URLSearchParams(params).toString();

        return api + '/' + path + (query ? '?' + query : '');
    };

    // ---- Tree -------------------------------------------------------------------------------

    function flatten(nodes, list = []) {
        for (const node of nodes) {
            list.push(node);
            flatten(node.children || [], list);
        }

        return list;
    }

    function renderTree() {
        treeList.replaceChildren(...state.tree.map((node) => treeNode(node, 1)));
    }

    function treeNode(node, level) {
        const active = state.current && !state.trash && state.current.disk === node.disk && state.current.path === node.path;
        const viewable = (node.rights || []).includes('view');
        const button = el('button', {
            type: 'button',
            class: 'btn btn-sm fm-tree-item text-start w-100' + (active ? ' active' : ''),
            'aria-current': active ? 'true' : null,
            disabled: !viewable && !(node.children || []).length ? true : null,
            onclick: () => {
                if (viewable) {
                    open(node.disk, node.path);
                    root.classList.remove('fm-tree-open');
                }
            },
        }, icon((node.icon || 'bi-folder') + ' me-1'), el('span', { text: node.name }));

        const item = el('li', { role: 'treeitem', 'aria-level': level, 'aria-expanded': (node.children || []).length ? 'true' : null }, button);

        if ((node.children || []).length) {
            item.append(el('ul', { class: 'list-unstyled fm-tree-children', role: 'group' }, node.children.map((child) => treeNode(child, level + 1))));
        }

        return item;
    }

    async function loadTree() {
        const data = await get(url('tree'));
        state.tree = Array.isArray(data.tree) ? data.tree : [];
        renderTree();

        return state.tree;
    }

    // ---- Listing ----------------------------------------------------------------------------

    function filterValues() {
        const values = {};

        for (const [key, value] of new FormData(filters).entries()) {
            if (String(value) !== '') {
                values[key] = String(value);
            }
        }

        return values;
    }

    async function open(disk, path) {
        state.trash = false;
        state.current = { disk, path };
        state.selected = null;
        closeDetails();
        renderTree();

        try {
            state.contents = await get(url('list', { disk, path, ...filterValues() }));
        } catch (error) {
            state.contents = null;
            listing.replaceChildren(el('p', { class: 'text-body-secondary', text: tx('loadFailed') }));

            return;
        }

        renderContents();
    }

    function refresh() {
        if (state.trash) {
            return openTrash();
        }

        return state.current ? open(state.current.disk, state.current.path) : Promise.resolve();
    }

    function renderContents() {
        const contents = state.contents;
        const readonly = contents.space === 'modules';

        crumbs.replaceChildren(...contents.breadcrumbs.map((crumb, index) => {
            const last = index === contents.breadcrumbs.length - 1;

            return el('li', { class: 'breadcrumb-item' + (last ? ' active' : ''), 'aria-current': last ? 'page' : null },
                last ? crumb.name : el('a', { href: '#', onclick: (event) => { event.preventDefault(); open(contents.disk, crumb.path); } }, crumb.name));
        }));

        quota.textContent = contents.quota ? tx('quota', formatSize(contents.quota.used), formatSize(contents.quota.limit)) : '';

        const show = (button, visible) => button && button.classList.toggle('d-none', !visible);
        show(buttons.upload, !readonly && options.can?.upload && can('upload'));
        show(buttons.newFolder, !readonly && options.can?.folders && (can('manage') || (contents.space === 'personal' && can('upload'))));
        show(buttons.renameFolder, !readonly && !contents.root && options.can?.folders && (can('manage') || (contents.space === 'personal' && can('upload'))));
        show(buttons.deleteFolder, !readonly && !contents.root && options.can?.folders && can('delete') && (can('manage') || contents.space === 'personal'));
        show(buttons.acl, !readonly && options.can?.acl && can('manage'));

        const children = [];

        for (const folder of contents.folders) {
            children.push({ type: 'folder', item: folder });
        }

        for (const file of contents.files) {
            children.push({ type: 'file', item: file });
        }

        if (!children.length) {
            listing.replaceChildren(el('div', { class: 'text-center text-body-secondary py-5' }, icon('bi-folder2 fs-1 d-block mb-2'), el('p', { class: 'mb-0', text: Object.keys(filterValues()).some((k) => k !== 'sort') ? tx('noMatches') : tx('empty') })));

            return;
        }

        listing.replaceChildren(state.view === 'grid' ? grid(children) : table(children));
    }

    function table(children) {
        const rows = children.map(({ type, item }) => {
            if (type === 'folder') {
                return el('tr', {},
                    el('td', { colspan: 4 }, el('button', { type: 'button', class: 'btn btn-link p-0 text-decoration-none', onclick: () => open(item.disk, item.path) }, icon('bi-folder-fill text-warning me-2'), item.name)));
            }

            return el('tr', { class: state.selected === item.id && item.id !== null ? 'table-active' : null },
                el('td', {}, el('button', { type: 'button', class: 'btn btn-link p-0 text-decoration-none text-start', onclick: () => select(item) },
                    item.thumbnail ? el('img', { src: item.thumbnail, alt: '', class: 'fm-mini me-2', loading: 'lazy' }) : icon(fileIcon(item) + ' me-2'), item.name)),
                el('td', { class: 'd-none d-md-table-cell text-body-secondary small', text: item.tags.join(', ') }),
                el('td', { class: 'text-end text-nowrap small', text: formatSize(item.size) }),
                el('td', { class: 'd-none d-sm-table-cell text-nowrap small text-body-secondary', text: String(item.created_at || '').slice(0, 16) }));
        });

        return el('div', { class: 'table-responsive' }, el('table', { class: 'table table-hover align-middle mb-0 fm-table' },
            el('thead', {}, el('tr', {},
                el('th', { scope: 'col', text: tx('name') }),
                el('th', { scope: 'col', class: 'd-none d-md-table-cell', text: tx('tags') }),
                el('th', { scope: 'col', class: 'text-end', text: tx('size') }),
                el('th', { scope: 'col', class: 'd-none d-sm-table-cell', text: tx('date') }))),
            el('tbody', {}, rows)));
    }

    function grid(children) {
        return el('ul', { class: 'fm-grid list-unstyled mb-0' }, children.map(({ type, item }) => {
            const thumb = type === 'folder'
                ? el('span', { class: 'fm-thumb' }, icon('bi-folder-fill text-warning'))
                : el('span', { class: 'fm-thumb' }, item.thumbnail ? el('img', { src: item.thumbnail, alt: item.alt || '', loading: 'lazy' }) : icon(fileIcon(item)));

            return el('li', {}, el('button', {
                type: 'button',
                class: 'fm-card btn' + (type === 'file' && state.selected === item.id && item.id !== null ? ' active' : ''),
                onclick: () => (type === 'folder' ? open(item.disk, item.path) : select(item)),
            }, thumb, el('span', { class: 'fm-card-name', text: item.name }), type === 'file' ? el('span', { class: 'small text-body-secondary', text: formatSize(item.size) }) : null));
        }));
    }

    // ---- Details ----------------------------------------------------------------------------

    function closeDetails() {
        details.classList.add('d-none');
        details.replaceChildren();
        root.classList.remove('fm-has-details');
    }

    function copy(text) {
        const absolute = new URL(text, window.location.href).href;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(absolute).then(() => toast(tx('copied'), { type: 'success' }), () => toast(absolute));
        } else {
            toast(absolute);
        }
    }

    function select(file) {
        state.selected = file.id;

        if (state.contents) {
            renderContents();
        }

        const rights = file.rights || [];
        const readonly = Boolean(file.readonly);
        const body = el('div', { class: 'card-body' });
        const close = el('button', { type: 'button', class: 'btn-close float-end', 'aria-label': tx('close'), onclick: () => { state.selected = null; closeDetails(); if (state.contents && !state.trash) { renderContents(); } } });

        body.append(close, el('h2', { class: 'h6 text-break pe-4', text: file.name }));

        if (file.image && file.preview) {
            body.append(el('a', { href: file.preview, target: '_blank', rel: 'noopener' }, el('img', { src: file.thumbnail || file.preview, alt: file.alt || '', class: 'img-fluid rounded border mb-3 fm-preview' })));
        } else if (file.preview && file.mime === 'application/pdf') {
            body.append(el('iframe', { src: file.preview, title: file.name, class: 'fm-pdf border rounded mb-3', sandbox: 'allow-same-origin' }));
        }

        const meta = el('dl', { class: 'row small mb-3' });
        const add = (label, value) => meta.append(el('dt', { class: 'col-5 text-body-secondary', text: label }), el('dd', { class: 'col-7 text-break', text: value }));
        add(tx('size'), formatSize(file.size));
        add(tx('typeLabel'), file.mime);

        if (file.width && file.height) {
            add(tx('dimensions'), file.width + ' × ' + file.height);
        }

        add(tx('visibility'), tx(file.visibility === 'public' ? 'public' : 'private'));
        add(tx('date'), String(file.created_at || '').slice(0, 16));
        body.append(meta);

        const actions = el('div', { class: 'd-flex flex-wrap gap-2 mb-3' });
        actions.append(el('a', { class: 'btn btn-sm btn-outline-secondary', href: file.download, download: true }, icon('bi-download me-1'), tx('download')));

        if (file.preview && !state.trash) {
            actions.append(el('a', { class: 'btn btn-sm btn-outline-secondary', href: file.preview, target: '_blank', rel: 'noopener' }, icon('bi-eye me-1'), tx('preview')));
        }

        if (!state.trash && (file.publicUrl || file.privateUrl)) {
            actions.append(el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: () => copy(file.publicUrl || file.privateUrl) }, icon('bi-link-45deg me-1'), tx(file.publicUrl ? 'copyPublic' : 'copyPrivate')));
        }

        body.append(actions);

        if (state.trash) {
            body.append(el('div', { class: 'd-flex flex-wrap gap-2' },
                el('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => fileAction(file, 'restore') }, icon('bi-arrow-counterclockwise me-1'), tx('restore')),
                el('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', onclick: () => fileAction(file, 'purge', tx('confirmPurge', file.name)) }, icon('bi-x-octagon me-1'), tx('purge'))));
        } else if (!readonly) {
            if (options.can?.edit && rights.includes('edit')) {
                body.append(metadataForm(file), el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary me-2 mb-2', onclick: () => moveFile(file) }, icon('bi-folder-symlink me-1'), tx('move')));
            } else if (file.alt || file.tags.length) {
                body.append(el('p', { class: 'small', text: [file.alt, file.tags.join(', ')].filter(Boolean).join(' · ') }));
            }

            if (options.can?.delete && rights.includes('delete')) {
                body.append(el('button', { type: 'button', class: 'btn btn-sm btn-outline-danger mb-2', onclick: () => fileAction(file, 'delete', tx('confirmTrash', file.name)) }, icon('bi-trash3 me-1'), tx('toTrash')));
            }
        }

        details.replaceChildren(body);
        details.classList.remove('d-none');
        root.classList.add('fm-has-details');
    }

    function metadataForm(file) {
        const form = el('form', { class: 'mb-3' });
        const field = (name, label, value, attributes = {}) => {
            const id = 'fm-' + name + '-' + file.id;

            return el('div', { class: 'mb-2' }, el('label', { class: 'form-label small mb-1', for: id, text: label }),
                el('input', { class: 'form-control form-control-sm', id, name, value, ...attributes }));
        };

        form.append(
            field('name', tx('name'), file.name, { required: true, maxlength: 255 }),
            field('alt', tx('alt'), file.alt, { maxlength: 255 }),
            field('tags', tx('tags'), file.tags.join(', '), { maxlength: 255, placeholder: tx('tagsHelp') }),
            el('button', { type: 'submit', class: 'btn btn-sm btn-primary' }, icon('bi-check-lg me-1'), tx('save')));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const body = new FormData(form);

            try {
                const data = await post(url('file/' + file.id + '/update'), { body });
                toast(data.message, { type: 'success' });
                await refresh();

                if (data.file) {
                    select(data.file);
                }
            } catch (error) {
                // toast already shown
            }
        });

        return form;
    }

    async function fileAction(file, action, question = null) {
        if (question !== null && !(await confirm(question, { variant: 'danger' }))) {
            return;
        }

        try {
            const data = await post(url('file/' + file.id + '/' + action));
            toast(data.message, { type: 'success' });
            state.selected = null;
            closeDetails();
            await refresh();
        } catch (error) {
            // toast already shown
        }
    }

    async function moveFile(file) {
        const targets = flatten(state.tree).filter((node) => node.disk === file.disk && (node.rights || []).includes('upload') && node.path !== file.folder && !node.readonly);

        if (!targets.length) {
            toast(tx('noTargets'), { type: 'warning' });

            return;
        }

        const select = el('select', { class: 'form-select', id: 'fm-move-target' }, targets.map((node) => el('option', { value: node.path, text: node.path })));
        const body = el('div', {}, el('label', { class: 'form-label', for: 'fm-move-target', text: tx('moveTo') }), select);
        const ok = await openModal({ title: tx('move'), body, buttons: [{ label: tx('cancel'), value: false, variant: 'secondary' }, { label: tx('move'), value: true, variant: 'primary' }] });

        if (ok !== true) {
            return;
        }

        try {
            const data = await post(url('file/' + file.id + '/move'), { body: formData({ to: select.value }) });
            toast(data.message, { type: 'success' });
            closeDetails();
            await refresh();
        } catch (error) {
            // toast already shown
        }
    }

    function formData(values) {
        const data = new FormData();

        for (const [key, value] of Object.entries(values)) {
            data.append(key, value);
        }

        return data;
    }

    // ---- Trash ------------------------------------------------------------------------------

    async function openTrash() {
        state.trash = true;
        state.contents = null;
        closeDetails();
        renderTree();
        Object.values(buttons).forEach((button) => button && button.classList.add('d-none'));
        crumbs.replaceChildren(el('li', { class: 'breadcrumb-item active', 'aria-current': 'page', text: tx('trash') }));
        quota.textContent = '';

        let data;

        try {
            data = await get(url('trash'));
        } catch (error) {
            return;
        }

        const files = Array.isArray(data.files) ? data.files : [];

        if (!files.length) {
            listing.replaceChildren(el('div', { class: 'text-center text-body-secondary py-5' }, icon('bi-trash3 fs-1 d-block mb-2'), el('p', { class: 'mb-0', text: tx('trashEmpty') })));

            return;
        }

        listing.replaceChildren(el('div', { class: 'table-responsive' }, el('table', { class: 'table table-hover align-middle mb-0' },
            el('thead', {}, el('tr', {}, el('th', { scope: 'col', text: tx('name') }), el('th', { scope: 'col', class: 'd-none d-md-table-cell', text: tx('folder') }), el('th', { scope: 'col', class: 'text-end', text: tx('size') }))),
            el('tbody', {}, files.map((file) => el('tr', {},
                el('td', {}, el('button', { type: 'button', class: 'btn btn-link p-0 text-decoration-none text-start', onclick: () => select(file) }, icon(fileIcon(file) + ' me-2'), file.name)),
                el('td', { class: 'd-none d-md-table-cell small text-body-secondary', text: file.folder }),
                el('td', { class: 'text-end small', text: formatSize(file.size) })))))));
    }

    // ---- Upload -----------------------------------------------------------------------------

    function csrf() {
        const header = document.querySelector('meta[name="csrf-header"]');
        const token = document.querySelector('meta[name="csrf-token"]');

        return header && token ? { header: header.content, token: token.content, element: token } : null;
    }

    function uploadOne(file, target) {
        const progress = el('progress', { class: 'fm-progress', max: 100, value: 0, 'aria-label': file.name });
        const status = el('span', { class: 'small text-body-secondary', text: tx('uploading') });
        const row = el('li', { class: 'fm-queue-item d-flex flex-wrap align-items-center gap-2 py-1' }, icon('bi-file-earmark-arrow-up'), el('span', { class: 'fm-queue-name text-truncate', text: file.name }), progress, status);
        queue.append(row);

        if (options.maxSize && file.size > options.maxSize) {
            progress.remove();
            status.textContent = tx('tooLarge', formatSize(options.maxSize));
            status.className = 'small text-danger';

            return Promise.resolve(false);
        }

        return new Promise((resolve) => {
            const request = new XMLHttpRequest();
            const body = formData({ disk: target.disk, path: target.path });
            body.append('files[]', file, file.name);
            request.open('POST', url('upload'));
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.setRequestHeader('Accept', 'application/json');
            const token = csrf();

            if (token) {
                request.setRequestHeader(token.header, token.token);
            }

            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    progress.value = Math.round((event.loaded / event.total) * 100);
                }
            });

            request.addEventListener('load', () => {
                if (token) {
                    const fresh = request.getResponseHeader(token.header);

                    if (fresh) {
                        token.element.content = fresh;
                    }
                }

                let data = null;

                try {
                    data = JSON.parse(request.responseText);
                } catch (error) {
                    data = null;
                }

                const result = data && Array.isArray(data.results) ? data.results[0] : null;
                const ok = request.status === 200 && result && result.ok;
                progress.value = 100;
                status.textContent = ok ? tx('done') : (result?.message || data?.message || tx('failed'));
                status.className = 'small ' + (ok ? 'text-success' : 'text-danger');

                if (ok) {
                    window.setTimeout(() => row.remove(), 2500);
                }

                resolve(ok);
            });

            request.addEventListener('error', () => {
                status.textContent = tx('failed');
                status.className = 'small text-danger';
                resolve(false);
            });

            request.send(body);
        });
    }

    async function upload(files) {
        if (!state.current || state.trash || !can('upload') || !options.can?.upload) {
            toast(tx('cannotUpload'), { type: 'warning' });

            return;
        }

        const target = { ...state.current };
        let uploaded = 0;

        for (const file of files) {
            if (await uploadOne(file, target)) {
                uploaded += 1;
            }
        }

        if (uploaded > 0) {
            toast(tx('uploaded', uploaded), { type: 'success' });
            await refresh();
        }
    }

    buttons.upload?.addEventListener('click', () => input.click());
    input?.addEventListener('change', () => {
        upload(Array.from(input.files || []));
        input.value = '';
    });

    let dragDepth = 0;
    drop.addEventListener('dragenter', (event) => {
        if (!event.dataTransfer || !Array.from(event.dataTransfer.types || []).includes('Files') || !can('upload')) {
            return;
        }

        event.preventDefault();
        dragDepth += 1;
        dropzone.classList.remove('d-none');
        drop.classList.add('fm-dragging');
    });
    drop.addEventListener('dragover', (event) => {
        if (can('upload')) {
            event.preventDefault();
        }
    });
    drop.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);

        if (dragDepth === 0) {
            dropzone.classList.add('d-none');
            drop.classList.remove('fm-dragging');
        }
    });
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        dragDepth = 0;
        dropzone.classList.add('d-none');
        drop.classList.remove('fm-dragging');
        upload(Array.from(event.dataTransfer?.files || []));
    });

    // ---- Folders ----------------------------------------------------------------------------

    async function askName(title, value = '') {
        const field = el('input', { class: 'form-control', id: 'fm-folder-name', value, maxlength: 80, required: true });
        const body = el('div', {}, el('label', { class: 'form-label', for: 'fm-folder-name', text: tx('folderName') }), field, el('div', { class: 'form-text', text: tx('folderNameHelp') }));
        const ok = await openModal({ title, body, buttons: [{ label: tx('cancel'), value: false, variant: 'secondary' }, { label: tx('save'), value: true, variant: 'primary' }] });

        return ok === true ? field.value.trim() : null;
    }

    buttons.newFolder?.addEventListener('click', async () => {
        const name = await askName(tx('newFolder'));

        if (!name) {
            return;
        }

        try {
            const data = await post(url('folder'), { body: formData({ ...state.current, name }) });
            toast(data.message, { type: 'success' });
            await loadTree();
            await open(state.current.disk, data.path);
        } catch (error) {
            // toast already shown
        }
    });

    buttons.renameFolder?.addEventListener('click', async () => {
        const name = await askName(tx('renameFolder'), state.contents?.name || '');

        if (!name) {
            return;
        }

        try {
            const data = await post(url('folder/rename'), { body: formData({ ...state.current, name }) });
            toast(data.message, { type: 'success' });
            await loadTree();
            await open(state.current.disk, data.path);
        } catch (error) {
            // toast already shown
        }
    });

    buttons.deleteFolder?.addEventListener('click', async () => {
        if (!(await confirm(tx('confirmDeleteFolder', state.contents?.name || ''), { variant: 'danger' }))) {
            return;
        }

        const parent = state.current.path.split('/').slice(0, -1).join('/');

        try {
            const data = await post(url('folder/delete'), { body: formData(state.current) });
            toast(data.message, { type: 'success' });
            await loadTree();
            await open(state.current.disk, parent);
        } catch (error) {
            // toast already shown
        }
    });

    // ---- Permissions ------------------------------------------------------------------------

    buttons.acl?.addEventListener('click', async () => {
        let data;

        try {
            data = await get(url('acl', state.current));
        } catch (error) {
            return;
        }

        const entries = el('tbody');
        const subjectSelect = (entry) => {
            const type = el('select', { class: 'form-select form-select-sm', 'aria-label': tx('subjectType') },
                ['group', 'user', 'authenticated'].map((value) => el('option', { value, selected: entry.subject_type === value ? true : null, text: tx('subject_' + value) })));
            const groups = el('select', { class: 'form-select form-select-sm', 'aria-label': tx('subject_group') },
                Object.entries(data.groups).map(([value, label]) => el('option', { value, selected: entry.subject_id === value ? true : null, text: label })));
            const user = el('input', { class: 'form-control form-control-sm', inputmode: 'numeric', pattern: '[0-9]+', placeholder: tx('userId'), 'aria-label': tx('userId'), value: entry.subject_type === 'user' ? entry.subject_id : '' });
            const sync = () => {
                groups.classList.toggle('d-none', type.value !== 'group');
                user.classList.toggle('d-none', type.value !== 'user');
            };
            type.addEventListener('change', sync);
            sync();

            return { type, groups, user };
        };

        const addRow = (entry = { subject_type: 'group', subject_id: '', rights: ['view'] }) => {
            const subject = subjectSelect(entry);
            const checks = data.rights.map((right) => {
                const id = 'fm-right-' + right + '-' + Math.random().toString(36).slice(2);

                return el('div', { class: 'form-check form-check-inline' },
                    el('input', { class: 'form-check-input', type: 'checkbox', id, value: right, checked: entry.rights.includes(right) ? true : null, 'data-right': true }),
                    el('label', { class: 'form-check-label small', for: id, text: tx('right_' + right) }));
            });
            const row = el('tr', { 'data-entry': true },
                el('td', {}, subject.type),
                el('td', {}, subject.groups, subject.user),
                el('td', {}, checks),
                el('td', {}, el('button', { type: 'button', class: 'btn btn-sm btn-link text-danger', 'aria-label': tx('remove'), onclick: () => row.remove() }, icon('bi-x-lg'))));
            row.read = () => ({
                subject_type: subject.type.value,
                subject_id: subject.type.value === 'group' ? subject.groups.value : (subject.type.value === 'user' ? subject.user.value.trim() : ''),
                rights: Array.from(row.querySelectorAll('[data-right]:checked')).map((box) => box.value),
            });
            entries.append(row);
        };

        (data.own ? data.entries : (data.inherited?.entries || [])).forEach((entry) => addRow(entry));

        const own = el('input', { class: 'form-check-input', type: 'checkbox', id: 'fm-acl-own', checked: data.own ? true : null });
        const table = el('div', { class: 'table-responsive' }, el('table', { class: 'table table-sm align-middle' },
            el('thead', {}, el('tr', {}, el('th', { text: tx('subject') }), el('th', { text: '' }), el('th', { text: tx('rights') }), el('th', { text: '' }))), entries));
        const addButton = el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: () => addRow() }, icon('bi-plus-lg me-1'), tx('addEntry'));
        const editor = el('div', { class: data.own ? '' : 'd-none' }, table, addButton);
        own.addEventListener('change', () => editor.classList.toggle('d-none', !own.checked));

        const body = el('div', {},
            el('p', { class: 'small text-body-secondary', text: data.inherited && !data.own ? tx('inheritsFrom', data.inherited.path) : tx('aclHelp') }),
            el('div', { class: 'form-check form-switch mb-3' }, own, el('label', { class: 'form-check-label', for: 'fm-acl-own', text: tx('ownPermissions') })),
            editor);

        let quotaInput = null;

        if (data.canQuota) {
            quotaInput = el('input', { class: 'form-control', type: 'number', min: 0, max: 1048576, id: 'fm-acl-quota', value: data.quota ? Math.round(data.quota / 1048576) : '' });
            body.append(el('div', { class: 'mt-3' }, el('label', { class: 'form-label', for: 'fm-acl-quota', text: tx('quotaMb') }), quotaInput, el('div', { class: 'form-text', text: tx('quotaHelp') })));
        }

        const ok = await openModal({ title: tx('permissionsOf', state.contents?.name || ''), body, size: 'lg', buttons: [{ label: tx('cancel'), value: false, variant: 'secondary' }, { label: tx('save'), value: true, variant: 'primary' }] });

        if (ok !== true) {
            return;
        }

        const payload = {
            ...state.current,
            own: own.checked,
            entries: Array.from(entries.querySelectorAll('[data-entry]')).map((row) => row.read()),
        };

        if (quotaInput !== null) {
            payload.quota = quotaInput.value === '' ? 0 : Number(quotaInput.value);
        }

        try {
            const result = await post(url('acl'), { json: payload });
            toast(result.message, { type: 'success' });
            await loadTree();
            await refresh();
        } catch (error) {
            // toast already shown
        }
    });

    // ---- Toolbar ----------------------------------------------------------------------------

    let timer = null;
    filters.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => refresh(), 300);
    });
    filters.addEventListener('submit', (event) => {
        event.preventDefault();
        refresh();
    });

    root.querySelectorAll('[data-fm-view]').forEach((button) => {
        button.setAttribute('aria-pressed', button.dataset.fmView === state.view ? 'true' : 'false');
        button.addEventListener('click', () => {
            state.view = button.dataset.fmView === 'grid' ? 'grid' : 'list';
            storeView(state.view);
            root.querySelectorAll('[data-fm-view]').forEach((other) => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));

            if (state.contents && !state.trash) {
                renderContents();
            }
        });
    });

    root.querySelector('[data-fm-trash]')?.addEventListener('click', () => openTrash());
    root.querySelector('[data-fm-toggle-tree]')?.addEventListener('click', (event) => {
        const opened = root.classList.toggle('fm-tree-open');
        event.currentTarget.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });

    if (treeNav) {
        treeNav.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                root.classList.remove('fm-tree-open');
            }
        });
    }

    loadTree().then((tree) => {
        const first = flatten(tree).find((node) => (node.rights || []).includes('view'));

        if (first) {
            open(first.disk, first.path);
        } else {
            listing.replaceChildren(el('p', { class: 'text-body-secondary', text: tx('nothing') }));
        }
    }).catch(() => {
        listing.replaceChildren(el('p', { class: 'text-body-secondary', text: tx('loadFailed') }));
    });
}
