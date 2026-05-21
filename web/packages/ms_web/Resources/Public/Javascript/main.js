'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // ----- Pricing billing-period toggle ----------------------------
    const toggleButtons = document.querySelectorAll('[data-mspricing-toggle]');
    if (toggleButtons.length > 0) {
        const setMode = (mode) => {
            toggleButtons.forEach((btn) => {
                const isActive = btn.getAttribute('data-mspricing-toggle') === mode;
                btn.classList.toggle('mspricing-toggle__button--active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            document.querySelectorAll('[data-mspricing-price]').forEach((el) => {
                el.hidden = el.getAttribute('data-mspricing-price') !== mode;
            });
        };
        toggleButtons.forEach((btn) => {
            btn.addEventListener('click', () => setMode(btn.getAttribute('data-mspricing-toggle')));
        });
    }

    // ----- Legal page: auto TOC from h2 headings --------------------
    const legalBody = document.querySelector('[data-legal-body] .legal-body');
    const legalTocNav = document.querySelector('[data-legal-toc]');
    if (legalBody && legalTocNav) {
        const headings = legalBody.querySelectorAll('h2');
        headings.forEach((h, idx) => {
            if (!h.id) {
                const slug = h.textContent.trim().toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                h.id = slug || `section-${idx + 1}`;
            }
            const a = document.createElement('a');
            a.href = `#${h.id}`;
            a.textContent = h.textContent;
            if (idx === 0) a.classList.add('active');
            legalTocNav.appendChild(a);
        });

        const links = legalTocNav.querySelectorAll('a');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    links.forEach((l) => l.classList.toggle(
                        'active',
                        l.getAttribute('href') === `#${entry.target.id}`,
                    ));
                }
            });
        }, { rootMargin: '-100px 0px -60% 0px' });
        headings.forEach((h) => observer.observe(h));
    }
});
