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
        const existingTitles = @json(collect($existingTitles)->map(fn ($t) => strtolower(trim($t)))->values());

        const titleInput = form.querySelector('[data-title]');
        const classification = form.querySelector('[data-classification]');
        const proponentsContainer = form.querySelector('[data-proponents]');
        const addButton = form.querySelector('[data-add-proponent]');
        const template = form.querySelector('[data-proponent-template]');
        let nextIndex = parseInt(proponentsContainer.dataset.nextIndex || '0', 10);

        function initProponentBlock(block) {
            const orgUnit = block.querySelector('[data-org-unit]');
            const position = block.querySelector('[data-position]');
            const schoolId = block.querySelector('[data-school-id]');
            const schoolIdHint = block.querySelector('[data-school-id-hint]');

            function currentUnitType() {
                const opt = orgUnit.options[orgUnit.selectedIndex];
                return opt ? (opt.dataset.type || '') : '';
            }

            function renderPositions() {
                const type = currentUnitType();
                const desired = position.dataset.old || position.value || '';
                position.innerHTML = '';
                const list = positionsByType[type] || [];
                if (! list.length) {
                    const placeholder = new Option('Select organizational unit first', '');
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

            function syncSchoolId() {
                const isSchool = currentUnitType() === 'school';
                if (! schoolId.disabled) schoolId.required = isSchool;
                schoolIdHint.classList.toggle('hidden', ! isSchool);
            }

            orgUnit.addEventListener('change', function () {
                position.dataset.old = '';
                renderPositions();
                syncSchoolId();
            });

            renderPositions();
            syncSchoolId();
        }

        function renumberTitles() {
            const blocks = proponentsContainer.querySelectorAll('[data-proponent]');
            blocks.forEach(function (block, i) {
                const title = block.querySelector('[data-proponent-title]');
                if (title && i > 0) title.textContent = 'Proponent ' + (i + 1);
            });
        }

        function syncClassification() {
            if (! titleInput || ! classification) return;
            const completed = classification.querySelector('option[value="completed"]');
            if (! completed || classification.disabled) return;
            const title = (titleInput.value || '').toLowerCase().trim();
            const matches = title !== '' && existingTitles.includes(title);
            completed.disabled = ! matches;
            if (! matches && classification.value === 'completed') {
                classification.value = 'proposal';
            }
            syncDocs();
        }

        function syncDocs() {
            if (! classification) return;
            const value = classification.value;
            form.querySelectorAll('[data-docs]').forEach(function (block) {
                const isActive = block.dataset.docs === value;
                block.classList.toggle('hidden', ! isActive);
                block.querySelectorAll('input[type="file"]').forEach(function (input) {
                    input.disabled = ! isActive;
                });
            });
        }

        proponentsContainer.querySelectorAll('[data-proponent]').forEach(initProponentBlock);

        if (addButton && template) {
            addButton.addEventListener('click', function () {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const node = wrapper.firstElementChild;
                proponentsContainer.appendChild(node);
                initProponentBlock(node);
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

        if (titleInput && classification) {
            classification.addEventListener('change', syncDocs);
            titleInput.addEventListener('input', syncClassification);
            syncDocs();
            syncClassification();
        }

        renumberTitles();
    })();
</script>
