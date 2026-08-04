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

function initChapterWizard(root) {
    const chapters = root.querySelector('[data-chapters]');
    const controls = root.querySelector('[data-wizard-controls]');

    if (! chapters || ! controls) {
        return;
    }

    const panels = Array.from(chapters.querySelectorAll('[data-chapter-panel]'));
    const chapterButtons = Array.from(controls.querySelectorAll('[data-wizard-chapter]'));

    if (! panels.length || ! chapterButtons.length) {
        return;
    }

    let currentIndex = 0;

    function render() {
        panels.forEach((panel, index) => panel.classList.toggle('hidden', index !== currentIndex));
        chapterButtons.forEach((button, index) => {
            const active = index === currentIndex;
            button.classList.toggle('bg-red-700', active);
            button.classList.toggle('border-red-700', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-300', ! active);
            button.classList.toggle('text-slate-700', ! active);
        });
    }

    chapterButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            currentIndex = index;
            render();
            panels[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    render();
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-section-editor-form]');

    if (! form) {
        return;
    }

    initRichTextEditors(form);
    initTableSections(form);
    initChapterWizard(form);

    form.addEventListener('submit', () => syncAllEditors(form));
});
