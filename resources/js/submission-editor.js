import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

function initRichTextEditors(root) {
    root.querySelectorAll('[data-richtext-editor]').forEach((el) => {
        if (el.dataset.ckeditorInitialized) {
            return;
        }

        el.dataset.ckeditorInitialized = '1';
        const hidden = document.getElementById(el.dataset.hiddenInput);

        ClassicEditor.create(el)
            .then((editor) => {
                el._ckeditorInstance = editor;
                editor.model.document.on('change:data', () => {
                    if (hidden) {
                        hidden.value = editor.getData();
                    }
                });
            })
            .catch((error) => console.error('Failed to initialize the editor', error));
    });
}

function syncAllEditors(root) {
    root.querySelectorAll('[data-richtext-editor]').forEach((el) => {
        const hidden = document.getElementById(el.dataset.hiddenInput);
        if (el._ckeditorInstance && hidden) {
            hidden.value = el._ckeditorInstance.getData();
        }
    });
}

function initTabs(root) {
    const tabButtons = root.querySelectorAll('[data-tab-button]');
    const tabPanels = root.querySelectorAll('[data-tab-panel]');

    if (! tabButtons.length) {
        return;
    }

    function activate(key) {
        tabButtons.forEach((btn) => {
            const isActive = btn.dataset.tabButton === key;
            btn.classList.toggle('bg-cyan-700', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('bg-slate-100', ! isActive);
            btn.classList.toggle('text-slate-600', ! isActive);
        });

        tabPanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.tabPanel !== key);
        });
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => activate(btn.dataset.tabButton));
    });

    activate(tabButtons[0].dataset.tabButton);
}

function initTableSections(root) {
    root.querySelectorAll('[data-table-section]').forEach((section) => {
        const rowsContainer = section.querySelector('[data-table-rows]');
        const template = section.querySelector('[data-row-template]');
        const addButton = section.querySelector('[data-add-row]');
        let nextIndex = parseInt(rowsContainer.dataset.nextIndex || '0', 10);

        function renumber() {
            rowsContainer.querySelectorAll('[data-table-row]').forEach((row, i) => {
                const label = row.querySelector('[data-row-number]');
                if (label) {
                    label.textContent = String(i + 1);
                }
            });
        }

        if (addButton && template) {
            addButton.addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = html.trim();
                rowsContainer.appendChild(wrapper.firstElementChild);
                nextIndex += 1;
                renumber();
            });
        }

        rowsContainer.addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-row]')) {
                event.target.closest('[data-table-row]').remove();
                renumber();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-section-editor-form]');

    if (! form) {
        return;
    }

    initRichTextEditors(form);
    initTabs(form);
    initTableSections(form);

    form.addEventListener('submit', () => syncAllEditors(form));
});
