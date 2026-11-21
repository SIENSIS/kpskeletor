/**
 * Gallery lightbox (`data-component="files/gallery"`, Views/sections/gallery.php): a click on an
 * image opens it in the section's `<dialog>` with its caption; the arrow keys and the buttons go
 * to the previous and next image, Escape (the dialog's own) or the close button closes it and
 * the focus goes back to the image that opened it. Without JavaScript the links open the image.
 *
 * `data-counter` is the text of the position ("{n} / {total}"), read by screen readers with the
 * caption.
 */
export function mount(element) {
    const dialog = element.querySelector('[data-lightbox]');
    const links = Array.from(element.querySelectorAll('[data-gallery-item]'));

    if (!dialog || links.length === 0 || typeof dialog.showModal !== 'function') {
        return;
    }

    const image = dialog.querySelector('[data-lightbox-image]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    const prev = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    const close = dialog.querySelector('[data-lightbox-close]');
    const counter = element.dataset.counter || '{n} / {total}';
    let current = 0;
    let opener = null;

    const show = (index) => {
        current = (index + links.length) % links.length;
        const link = links[current];
        image.src = link.href;
        image.alt = link.dataset.alt || link.dataset.caption || '';
        const position = counter.replace('{n}', String(current + 1)).replace('{total}', String(links.length));
        caption.textContent = (link.dataset.caption ? link.dataset.caption + ' · ' : '') + position;
    };

    const single = links.length === 1;
    prev.hidden = single;
    next.hidden = single;

    links.forEach((link, index) => {
        link.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
                return; // a new tab or window: the browser opens the image
            }

            event.preventDefault();
            opener = link;
            show(index);
            dialog.showModal();
            close.focus();
        });
    });

    prev.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    close.addEventListener('click', () => dialog.close());

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' && !single) {
            event.preventDefault();
            show(current - 1);
        } else if (event.key === 'ArrowRight' && !single) {
            event.preventDefault();
            show(current + 1);
        }
    });

    // A click on the backdrop (outside the inner box) closes the dialog.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    element.dataset.ready = '1';

    dialog.addEventListener('close', () => {
        image.removeAttribute('src');

        if (opener) {
            opener.focus();
        }
    });
}
