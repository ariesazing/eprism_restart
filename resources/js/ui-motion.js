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

// Guest welcome page's "From submission to completion" steps, staggered in once the
// section scrolls into view. No-ops on every other page (the selector finds nothing).
// Unlike the butterfly above, this one has to actually hide content before revealing
// it — so the hidden state is applied here, in JS, immediately before observing,
// rather than in CSS: if JS never runs (or IntersectionObserver is unsupported), the
// steps are simply never hidden in the first place, instead of stuck invisible.
const workflow = document.querySelector('.guest-workflow');
const workflowSteps = workflow ? [...workflow.querySelectorAll('li')] : [];

if (workflowSteps.length && !reducedMotion.matches && 'IntersectionObserver' in window) {
    workflowSteps.forEach((step, index) => {
        step.style.opacity = '0';
        step.style.transform = 'translateY(14px)';
        step.style.transitionDelay = `${index * 60}ms`;
    });

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        workflowSteps.forEach((step) => {
            step.style.opacity = '';
            step.style.transform = '';
        });
        observer.disconnect();
    }, { threshold: 0.25 });
    observer.observe(workflow);
}
