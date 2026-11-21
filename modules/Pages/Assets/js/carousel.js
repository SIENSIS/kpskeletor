/**
 * Carousel (`data-component="pages/carousel"`, Views/sections/carousel.php; D-46-02).
 *
 * - One slide at a time (the others get `hidden`); arrows, dots (`aria-pressed`) and the left
 *   and right arrow keys while the focus is inside the carousel.
 * - **No automatic rotation by default.** With `data-rotate="1"` it moves every `data-interval`
 *   seconds (at least 6), stops while the pointer is over it or the focus is inside it, and the
 *   pause button stops it until it is pressed again. With `prefers-reduced-motion: reduce` it
 *   never rotates (and the pause button is hidden).
 * - Without JavaScript the slides stay one under the other.
 */
const MIN_INTERVAL = 6;

export function mount(element) {
    const slides = Array.from(element.querySelectorAll('[data-carousel-slide]'));
    const controls = element.querySelector('[data-carousel-controls]');

    if (slides.length < 2 || !controls) {
        return;
    }

    const dots = Array.from(controls.querySelectorAll('[data-carousel-dot]'));
    const pause = controls.querySelector('[data-carousel-pause]');
    const live = element.querySelector('[data-carousel-slides]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const seconds = Math.max(MIN_INTERVAL, Number.parseInt(element.dataset.interval || '8', 10) || 8);
    let current = 0;
    let timer = null;
    let paused = false;     // by the pause button
    let suspended = false;  // pointer over it or focus inside it

    const show = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.hidden = i !== current;
        });
        dots.forEach((dot, i) => dot.setAttribute('aria-pressed', i === current ? 'true' : 'false'));
    };

    const canRotate = () => element.dataset.rotate === '1' && !reduced.matches;

    const stop = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        stop();

        if (canRotate() && !paused && !suspended) {
            timer = window.setInterval(() => show(current + 1), seconds * 1000);
        }
    };

    element.classList.add('is-enhanced');
    controls.hidden = false;
    show(0);

    controls.querySelector('[data-carousel-prev]').addEventListener('click', () => {
        show(current - 1);
        start();
    });
    controls.querySelector('[data-carousel-next]').addEventListener('click', () => {
        show(current + 1);
        start();
    });
    dots.forEach((dot, i) => dot.addEventListener('click', () => {
        show(i);
        start();
    }));

    element.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            show(current + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });

    if (pause) {
        const syncPause = () => {
            pause.hidden = !canRotate();
            pause.setAttribute('aria-label', paused ? element.dataset.textPlay : element.dataset.textPause);
            pause.firstElementChild.className = paused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        };

        pause.addEventListener('click', () => {
            paused = !paused;
            syncPause();
            start();
        });

        reduced.addEventListener('change', () => {
            syncPause();
            start();
        });

        syncPause();
    }

    element.addEventListener('mouseenter', () => {
        suspended = true;
        stop();
    });
    element.addEventListener('mouseleave', () => {
        suspended = element.contains(document.activeElement);
        start();
    });
    element.addEventListener('focusin', () => {
        suspended = true;
        stop();
    });
    element.addEventListener('focusout', (event) => {
        if (!element.contains(event.relatedTarget)) {
            suspended = element.matches(':hover');
            start();
        }
    });

    if (live && canRotate()) {
        live.setAttribute('aria-live', 'off');
    }

    start();
}
