{{--
    Reusable spinner -> checkmark overlay for a form submitted via submitWithFeedback()
    (resources/js/app.js) instead of a plain page-navigating POST — used by the
    researcher's Submit/Resubmit actions and the reviewer's evaluation submit. Deliberately
    plain DOM/vanilla-JS driven (not Alpine): the JS doing the fetch() is also the thing
    toggling this modal's state, so there's no cross-framework event choreography to wire
    up. Hidden by default; submitWithFeedback() shows/hides it and swaps its content.
--}}
<div data-submit-feedback-modal class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/60 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-xl">
        <div data-submit-feedback-spinner class="mx-auto flex h-14 w-14 items-center justify-center">
            <span class="doc-spinner"></span>
        </div>
        <div data-submit-feedback-success class="mx-auto hidden h-14 w-14 items-center justify-center">
            <svg viewBox="0 0 52 52" class="submit-feedback-check">
                <circle class="submit-feedback-check-circle" cx="26" cy="26" r="24" fill="none" />
                <path class="submit-feedback-check-mark" fill="none" d="M14 27l7 7 16-16" />
            </svg>
        </div>
        <p data-submit-feedback-message class="mt-4 text-sm font-medium text-slate-700">Submitting&hellip;</p>
    </div>
</div>
