/**
 * Progress of a restore (`data-component="backup/restore-progress"`, modules/Backup/Views/job.php).
 * Polls `progress/backup/<job>` (JSON: status, step label, percentage) every two seconds and
 * reloads the page (`data-page`) when the job ends, which then shows the result. The endpoint is
 * reachable while maintenance mode is on; other pages answer 503 meanwhile.
 */
const INTERVAL = 2000;

function show(root, data) {
    const bar = root.querySelector('[data-progress-bar]');
    const label = root.querySelector('[data-progress-label]');
    const percent = Math.max(0, Math.min(100, Number.parseInt(String(data.percent ?? 0), 10) || 0));

    if (bar) {
        bar.style.width = percent + '%';
        bar.parentElement?.setAttribute('aria-valuenow', String(percent));
    }

    if (label && typeof data.label === 'string' && data.label !== '') {
        label.textContent = data.label;
    }
}

export function mount(root) {
    const url = root.dataset.url;
    const page = root.dataset.page;
    const bar = root.querySelector('[data-progress-bar]');

    if (bar) {
        bar.style.width = (Number.parseInt(bar.dataset.percent || '0', 10) || 0) + '%';
    }

    if (!url) {
        return;
    }

    const poll = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });

            if (response.ok) {
                const data = await response.json();
                show(root, data);

                if (data.status === 'done' || data.status === 'failed') {
                    root.querySelector('[data-progress-finished]')?.classList.remove('d-none');
                    window.setTimeout(() => window.location.assign(page), 800);

                    return;
                }
            }
        } catch (error) {
            // The server may be busy restoring: try again.
        }

        window.setTimeout(poll, INTERVAL);
    };

    window.setTimeout(poll, 500);
}
