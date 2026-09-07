// Real-time (as-you-type) validation for auth, account-management, and account-creation
// forms — previously every one of these forms only ever told you something was wrong
// after a full submit-and-reload round trip. This is purely a progressive client-side
// layer: it never blocks submission and never replaces server-side validation (a field
// this can't check at all, e.g. email uniqueness, still only gets caught on submit) — it
// just gives immediate feedback for the checks that genuinely don't need the server
// (required, format, length, password complexity, confirmation match).
//
// Usage: add data-rules="required|email" (pipe-separated) to an <input>, and put
// data-error-for="<name>" (via <x-input-error :field="'email'" .../>) on the <ul> that
// should receive the messages — see resources/views/components/input-error.blade.php.

const VALIDATORS = {
    required: () => (value) => (value.trim() !== '' ? null : 'This field is required.'),

    email: () => (value) => (value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? null : 'Enter a valid email address.'),

    min: (n) => (value) => (value === '' || value.length >= Number(n) ? null : `Must be at least ${n} characters.`),

    // Mirrors Illuminate\Validation\Rules\Password::defaults() as configured across this
    // app's registration/account-creation/password-update forms: 8+ characters, at least
    // one of each of lowercase/uppercase/number/symbol.
    password: () => (value) => {
        if (value === '') {
            return null;
        }

        if (value.length < 8) {
            return 'Must be at least 8 characters.';
        }

        const missing = [
            [/[a-z]/, 'a lowercase letter'],
            [/[A-Z]/, 'an uppercase letter'],
            [/[0-9]/, 'a number'],
            [/[^a-zA-Z0-9]/, 'a symbol'],
        ]
            .filter(([pattern]) => !pattern.test(value))
            .map(([, label]) => label);

        return missing.length ? `Must include ${missing.join(', ')}.` : null;
    },

    // matches:otherFieldName — for a confirm-password field. Only complains once *both*
    // fields have something in them, so it doesn't flag "doesn't match" while the second
    // field is simply still empty.
    matches: (otherFieldName) => (value, form) => {
        const other = form?.querySelector(`[name="${otherFieldName}"]`);

        if (!other || value === '' || other.value === '') {
            return null;
        }

        return value === other.value ? null : 'Does not match.';
    },
};

function parseRuleToken(token) {
    const [name, arg] = token.split(':');
    const factory = VALIDATORS[name];

    return factory ? factory(arg) : null;
}

function debounce(fn, waitMs) {
    let timeout;

    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), waitMs);
    };
}

function errorListFor(input) {
    const form = input.closest('form');

    return form?.querySelector(`[data-error-for="${input.name}"]`) ?? null;
}

function renderErrors(input, messages) {
    const list = errorListFor(input);

    if (!list) {
        return;
    }

    list.innerHTML = messages.map((message) => `<li>${message}</li>`).join('');
    input.setAttribute('aria-invalid', messages.length ? 'true' : 'false');
}

function validateField(input) {
    const form = input.closest('form');
    const rules = (input.dataset.rules || '').split('|').filter(Boolean);
    const messages = [];

    for (const token of rules) {
        const validator = parseRuleToken(token);

        if (!validator) {
            continue;
        }

        // One message at a time (matches how a real submit's error list reads for a
        // single field in this app already) — stop at the first rule that fails rather
        // than piling up every violation at once.
        const message = validator(input.value, form);

        if (message) {
            messages.push(message);
            break;
        }
    }

    renderErrors(input, messages);

    return messages.length === 0;
}

/**
 * Wires every not-yet-wired [data-rules] input under `root`. Safe to call more than once
 * on the same document (e.g. after an admin modal's content is swapped) — already-wired
 * inputs are skipped via a dataset flag.
 */
export function wireInlineValidation(root = document) {
    root.querySelectorAll('[data-rules]').forEach((input) => {
        if (input.dataset.liveValidationWired) {
            return;
        }

        input.dataset.liveValidationWired = '1';

        const debouncedValidate = debounce(() => validateField(input), 300);

        input.addEventListener('input', debouncedValidate);
        input.addEventListener('blur', () => validateField(input));

        // A confirm-password field's own correctness depends on the *other* password
        // field too — re-check it live as that other field changes, not just itself.
        const matchToken = (input.dataset.rules || '').split('|').find((token) => token.startsWith('matches:'));

        if (matchToken) {
            const otherField = input.closest('form')?.querySelector(`[name="${matchToken.split(':')[1]}"]`);
            otherField?.addEventListener('input', debounce(() => validateField(input), 300));
        }
    });
}
