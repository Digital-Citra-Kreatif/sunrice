// Lumina Estates: progressive enhancement only. Every page works without this script.
import { dropEmptyFields, setPageInert, submitOnPointerChange } from './enhance.js';

document.documentElement.classList.add('js');

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Header: solid once the page scrolls (over a hero it starts transparent).
const header = document.querySelector('[data-site-header]');
if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const toggle = header.querySelector('[data-nav-toggle]');
    const setOpen = (open) => {
        header.classList.toggle('is-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
        setPageInert(header, open);
    };
    toggle?.addEventListener('click', () => setOpen(!header.classList.contains('is-open')));
    // The menu is for small screens: growing past the breakpoint closes it, so the page isn't left inert.
    window.matchMedia('(min-width: 64rem)').addEventListener('change', (event) => {
        if (event.matches && header.classList.contains('is-open')) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('is-open')) {
            toggle?.click();
            toggle?.focus();
        }
    });
}

// Reveal sections as they enter the viewport.
const revealables = document.querySelectorAll('.reveal');
if (!reduceMotion && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }
    }, { rootMargin: '0px 0px -8% 0px' });
    revealables.forEach((element) => observer.observe(element));
} else {
    revealables.forEach((element) => element.classList.add('is-visible'));
}

// Gallery viewer: a <dialog> with previous/next, keyboard arrows and swipe.
for (const gallery of document.querySelectorAll('[data-gallery]')) {
    const dialog = document.getElementById(gallery.dataset.gallery);
    if (!(dialog instanceof HTMLDialogElement)) continue;
    const triggers = [...gallery.querySelectorAll('[data-index]')];
    const items = JSON.parse(dialog.dataset.items || '[]');
    const image = dialog.querySelector('img');
    const caption = dialog.querySelector('[data-caption]');
    const counter = dialog.querySelector('[data-counter]');
    let index = 0;
    let lastTrigger = null;

    const show = (next) => {
        index = (next + items.length) % items.length;
        const item = items[index];
        image.src = item.src;
        image.srcset = item.srcset || '';
        image.alt = item.alt || '';
        caption.textContent = item.alt || '';
        counter.textContent = `${index + 1} / ${items.length}`;
        // restart the fade
        image.style.animation = 'none';
        void image.offsetWidth;
        image.style.animation = '';
    };

    triggers.forEach((trigger) => trigger.addEventListener('click', () => {
        lastTrigger = trigger;
        show(Number(trigger.dataset.index));
        dialog.showModal();
    }));
    dialog.querySelector('[data-prev]')?.addEventListener('click', () => show(index - 1));
    dialog.querySelector('[data-next]')?.addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => lastTrigger?.focus());
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') show(index - 1);
        if (event.key === 'ArrowRight') show(index + 1);
    });
    let startX = null;
    dialog.addEventListener('touchstart', (event) => { startX = event.touches[0].clientX; }, { passive: true });
    dialog.addEventListener('touchend', (event) => {
        if (startX === null) return;
        const delta = event.changedTouches[0].clientX - startX;
        if (Math.abs(delta) > 40) show(index + (delta < 0 ? 1 : -1));
        startX = null;
    });
}

// Filters: a select changed with the mouse or touch submits the form; keyboard users submit with the button.
for (const form of document.querySelectorAll('[data-autosubmit]')) {
    submitOnPointerChange(form);
}
for (const form of document.querySelectorAll('form[method="get"]')) {
    dropEmptyFields(form);
}
