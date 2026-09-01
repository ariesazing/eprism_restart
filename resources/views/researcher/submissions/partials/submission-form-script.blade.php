@php
    $positionsByType = [
        'school' => $schoolPositions->pluck('label')->values(),
        'non_school' => $nonSchoolPositions->pluck('label')->values(),
    ];
@endphp
<script>
    (function () {
        const form = document.querySelector('[data-submission-form]');
        if (! form) return;

        const positionsByType = @json($positionsByType);

        const orgUnit = form.querySelector('[data-org-unit]');
        // Deliberately a different attribute than the <option>s' own `data-school-id`
        // marker (used below to read each school's canonical ID) — `[data-school-id]`
        // would otherwise match the *first* such <option> before it ever reaches this
        // hidden input, silently rewriting that option's value instead of this field's.
        const schoolId = form.querySelector('[data-school-id-input]');
        const schoolIdDisplay = form.querySelector('[data-school-id-display]');
        const schoolIdValueEl = form.querySelector('[data-school-id-value]');

        const proponentsContainer = form.querySelector('[data-proponents]');
        const addButton = form.querySelector('[data-add-proponent]');
        const template = form.querySelector('[data-proponent-template]');
        let nextIndex = parseInt(proponentsContainer.dataset.nextIndex || '0', 10);

        function currentUnitType() {
            if (! orgUnit) return '';
            const opt = orgUnit.options[orgUnit.selectedIndex];
            return opt ? (opt.dataset.type || '') : '';
        }

        function renderPositionsForBlock(block) {
            const position = block.querySelector('[data-position]');
            if (! position) return;

            const type = currentUnitType();
            const desired = position.dataset.old || position.value || '';
            position.innerHTML = '';
            const list = positionsByType[type] || [];
            if (! list.length) {
                const placeholder = new Option('Select school/station first', '');
                placeholder.disabled = true;
                placeholder.selected = true;
                position.add(placeholder);
                return;
            }
            list.forEach(function (label) {
                const option = new Option(label, label);
                if (label === desired) option.selected = true;
                position.add(option);
            });
        }

        function renderAllPositions() {
            proponentsContainer.querySelectorAll('[data-proponent]').forEach(renderPositionsForBlock);
        }

        // The school ID has no editable input of its own — it's entirely derived from
        // whichever school/station is currently selected (OrganizationalUnit::school_id),
        // via this hidden field's value plus a read-only confirmation line for the
        // researcher. Non-school stations simply carry no ID.
        function syncSchoolId() {
            if (! schoolId) return;
            const opt = orgUnit ? orgUnit.options[orgUnit.selectedIndex] : null;
            const canonicalSchoolId = opt ? (opt.dataset.schoolId || '') : '';
            schoolId.value = canonicalSchoolId;
            if (schoolIdValueEl) schoolIdValueEl.textContent = canonicalSchoolId;
            if (schoolIdDisplay) schoolIdDisplay.classList.toggle('hidden', ! canonicalSchoolId);
        }

        function renumberTitles() {
            const blocks = proponentsContainer.querySelectorAll('[data-proponent]');
            blocks.forEach(function (block, i) {
                const title = block.querySelector('[data-proponent-title]');
                if (title && i > 0) title.textContent = 'Proponent ' + (i + 1);
            });
        }

        if (orgUnit) {
            orgUnit.addEventListener('change', function () {
                proponentsContainer.querySelectorAll('[data-position]').forEach(function (position) {
                    position.dataset.old = '';
                });
                renderAllPositions();
                syncSchoolId();
            });
        }

        renderAllPositions();
        syncSchoolId();

        if (addButton && template) {
            addButton.addEventListener('click', function () {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const node = wrapper.firstElementChild;
                proponentsContainer.appendChild(node);
                renderPositionsForBlock(node);
                renumberTitles();
                nextIndex++;
            });
        }

        proponentsContainer.addEventListener('click', function (e) {
            if (e.target.matches('[data-remove-proponent]')) {
                e.target.closest('[data-proponent]').remove();
                renumberTitles();
            }
        });

        // Delegated so a proponent block added later (cloned from the <template> above)
        // gets a working preview without any extra wiring.
        proponentsContainer.addEventListener('change', function (e) {
            if (! e.target.matches('[data-photo-input]')) return;

            const block = e.target.closest('[data-proponent]');
            const preview = block ? block.querySelector('[data-photo-preview]') : null;
            if (! preview) return;

            if (preview.dataset.objectUrl) {
                URL.revokeObjectURL(preview.dataset.objectUrl);
                delete preview.dataset.objectUrl;
            }

            const file = e.target.files && e.target.files[0];
            if (! file) {
                preview.src = '';
                preview.classList.add('hidden');
                return;
            }

            const url = URL.createObjectURL(file);
            preview.dataset.objectUrl = url;
            preview.src = url;
            preview.classList.remove('hidden');
        });

        // Attachment removal is a plain AJAX call, not a nested <form> (see
        // attachments-editor.blade.php) — a <form> here would be invalid inside this
        // page's single outer draft form.
        form.addEventListener('click', function (e) {
            const button = e.target.closest('[data-attachment-remove]');
            if (! button) return;

            e.preventDefault();

            if (! confirm('Remove this attachment?')) return;

            button.disabled = true;

            window.axios.delete(button.dataset.attachmentUrl).then(function () {
                button.closest('li').remove();
            }).catch(function (error) {
                console.error('Failed to remove attachment', error);
                button.disabled = false;
                window.alert('Could not remove this attachment. Please try again.');
            });
        });

        renumberTitles();
    })();
</script>
