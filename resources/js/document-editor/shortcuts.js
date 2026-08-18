import { KeyMap } from '@hufe921/canvas-editor';

// Registers ONLY the shortcuts canvas-editor doesn't already bind internally, through its
// public register.shortcutList() extension point — never a competing keydown listener.
// Everything already native (Ctrl/Cmd+B/I/U, Ctrl/Cmd+Z undo, Ctrl/Cmd+Y redo, Ctrl/Cmd+S
// save, alignment, headings, lists, …) is left completely alone; see
// https://hufe.club/canvas-editor-docs/guide/shortcut-internal.html for the full native
// list this deliberately doesn't touch.
export function registerAdditionalShortcuts(editor, { onFind, onInsertLink }) {
    editor.register.shortcutList([
        {
            key: KeyMap.F,
            mod: true,
            callback: () => onFind(),
        },
        {
            key: KeyMap.K,
            mod: true,
            callback: () => onInsertLink(),
        },
        {
            // canvas-editor natively binds Ctrl/Cmd+Y for redo, not the also-common
            // Ctrl/Cmd+Shift+Z — this adds that as an alias rather than replacing the
            // native binding.
            key: KeyMap.Z_UPPERCASE,
            mod: true,
            shift: true,
            callback: (command) => command.executeRedo(),
        },
    ]);
}

/**
 * Dispatches the same Tab/Shift+Tab keydown canvas-editor's own shortcut handler listens
 * for, to trigger its documented (but not separately exposed as a command) list-indent
 * behavior. Only meaningful while the cursor is inside a list — callers should gate this
 * on rangeStyle.listType !== null.
 */
export function dispatchListIndent(editor, outdent = false) {
    // canvas-editor appends a hidden `textarea.ce-inputarea` to its container and binds
    // keydown there (confirmed by reading the shipped bundle, not guessed) — dispatching
    // to it is indistinguishable from a real keypress to that handler.
    const target = editor.command.getContainer().querySelector('.ce-inputarea, textarea') ?? editor.command.getContainer();

    target.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Tab',
        code: 'Tab',
        shiftKey: outdent,
        bubbles: true,
        cancelable: true,
    }));
}
