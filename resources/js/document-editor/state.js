// Single subscription to canvas-editor's own state-change hooks, fanned out to every
// toolbar group that needs to react — so no group polls the editor independently and the
// toolbar can never show stale formatting info (rangeStyleChange fires on every cursor
// move/selection change; contentChange fires on every edit, e.g. for word count).

export function createToolbarState(editor) {
    const rangeStyleListeners = [];
    const contentChangeListeners = [];

    editor.listener.rangeStyleChange = (payload) => {
        rangeStyleListeners.forEach((listener) => listener(payload));
    };

    editor.listener.contentChange = () => {
        contentChangeListeners.forEach((listener) => listener());
    };

    return {
        onRangeStyleChange(listener) {
            rangeStyleListeners.push(listener);
        },
        onContentChange(listener) {
            contentChangeListeners.push(listener);
        },
    };
}
