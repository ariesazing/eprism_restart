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
        const schoolId = form.querySelector('[data-school-id]');
        const schoolIdHint = form.querySelector('[data-school-id-hint]');

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

        function syncSchoolId() {
            if (! schoolId) return;
            const isSchool = currentUnitType() === 'school';
            if (! schoolId.disabled) schoolId.required = isSchool;
            if (schoolIdHint) schoolIdHint.classList.toggle('hidden', ! isSchool);
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

        renumberTitles();
    })();
</script>
