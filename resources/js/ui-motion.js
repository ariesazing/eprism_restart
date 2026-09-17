// Pointer feedback is animated; keyboard actions stay immediate.
document.addEventListener('keydown', () => {
    document.documentElement.dataset.inputModality = 'keyboard';
}, { capture: true });
document.addEventListener('pointerdown', () => {
    document.documentElement.dataset.inputModality = 'pointer';
}, { capture: true, passive: true });

const butterfly = document.querySelector('.studio-butterfly');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// Only the decorative landing artwork flutters, once per page visit.
// No hidden content or animation dependency when JS/IntersectionObserver is absent.
if (butterfly && !reducedMotion.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        butterfly.dataset.introduced = 'true';
        observer.disconnect();
    }, { threshold: 0.6 });
    observer.observe(butterfly);
    reducedMotion.addEventListener('change', (event) => {
        if (event.matches) {
            observer.disconnect();
            delete butterfly.dataset.introduced;
        }
    });
}
