/**
 * Web installer progress (`data-component="core/install/progress"`, session 39).
 *
 * - On the installer's form: sets the hidden `progress` field to 1, so the server answers the
 *   progress page (it runs the installation after the response when it can), and disables the
 *   submit button once sent. Without this script the field stays 0 and the installation runs
 *   inside the request, as before.
 * - On the progress page: polls `data-url` (`progress/install/<job>`: status, step label,
 *   percentage, log lines), moves the bar (`role="progressbar"`, `aria-valuenow`), adds the new
 *   log lines to the list (`aria-live="polite"`) and opens `data-next` (the result page) when
 *   the job is done or failed. An unknown or expired job shows `data-lost`.
 */
const INTERVAL = 1000;
const SLOW_INTERVAL = 3000;

function clamp(value) {
    return Math.max(0, Math.min(100, Number.parseInt(String(value ?? 0), 10) || 0));
}

function mountForm(form) {
    const flag = form.querySelector('[data-install-progress-flag]');

    if (flag) {
        flag.value = '1';
    }

    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-install-submit]');

        if (button) {
            // After this event, so the browser still sends the form.
            window.setTimeout(() => {
                button.disabled = true;
            }, 0);
        }
    });
}

function setPercent(root, percent) {
    const bar = root.querySelector('[data-progress-bar]');
    const text = root.querySelector('[data-progress-percent]');

    if (bar) {
        bar.style.width = percent + '%';
    }

    root.querySelector('[data-progress]')?.setAttribute('aria-valuenow', String(percent));

    if (text) {
        text.textContent = text.textContent.replace(/\d+/, String(percent));
    }
}

function showLog(root, lines) {
    const list = root.querySelector('[data-progress-log]');

    if (!list || !Array.isArray(lines)) {
        return;
    }

    // Only the new lines are added, so screen readers announce just those.
    for (let index = list.children.length; index < lines.length; index++) {
        if (typeof lines[index] !== 'string' || lines[index] === '') {
            continue;
        }

        const item = document.createElement('li');
        item.className = 'list-group-item py-1';
        item.textContent = lines[index];
        list.append(item);
    }
}

function stop(root, kind) {
    const bar = root.querySelector('[data-progress-bar]');

    bar?.classList.remove('progress-bar-animated', 'progress-bar-striped');
    bar?.classList.add(kind === 'done' ? 'bg-success' : 'bg-danger');
    root.querySelector('[data-progress-spinner]')?.classList.add('d-none');
}

function mountProgress(root) {
    const url = root.dataset.url;
    const next = root.dataset.next;

    setPercent(root, clamp(root.querySelector('[data-progress-bar]')?.dataset.percent));

    if (!url) {
        return;
    }

    const poll = async () => {
        let delay = INTERVAL;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });

            if (response.status === 404) {
                const error = root.querySelector('[data-progress-error]');

                if (error) {
                    error.textContent = root.dataset.lost || '';
                    error.classList.remove('d-none');
                }

                stop(root, 'failed');

                return;
            }

            if (response.ok) {
                const data = await response.json();
                const label = root.querySelector('[data-progress-label]');

                setPercent(root, clamp(data.percent));
                showLog(root, data.log);

                if (label && typeof data.label === 'string' && data.label !== '') {
                    label.textContent = data.label;
                }

                if (data.status === 'done' || data.status === 'failed') {
                    stop(root, data.status);
                    window.setTimeout(() => window.location.assign(next), 800);

                    return;
                }
            } else {
                delay = SLOW_INTERVAL; // Rate limited or busy: slow down.
            }
        } catch (error) {
            delay = SLOW_INTERVAL; // The server may be busy installing: try again.
        }

        window.setTimeout(poll, delay);
    };

    window.setTimeout(poll, 300);
}

export function mount(root) {
    if (root instanceof HTMLFormElement) {
        mountForm(root);

        return;
    }

    mountProgress(root);
}
