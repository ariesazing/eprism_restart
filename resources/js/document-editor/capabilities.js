// Single source of truth for what canvas-editor (@hufe921/canvas-editor) actually
// supports, verified against its shipped .d.ts files and docs — not assumed. toolbar.js
// reads this to decide what to render, disable, or annotate, so "don't fake a feature"
// is enforced in code rather than left to convention.
//
// Each entry: supported (bool), and — when false — why, so the UI can say something
// honest instead of just vanishing without explanation.

export const CAPABILITIES = {
    title_subtitle_styles: {
        supported: false,
        reason: 'canvas-editor only exposes 6 heading levels (TitleLevel.FIRST–SIXTH) plus Normal text — there is no separate "Title"/"Subtitle" preset style.',
    },
    paragraph_spacing: {
        supported: false,
        reason: 'canvas-editor has a single line-spacing value (rowMargin), not separate space-before/space-after paragraph spacing.',
    },
    paragraph_indent: {
        supported: false,
        reason: 'There is no indent property or command for a plain (non-list) paragraph anywhere in canvas-editor.',
    },
    list_indent: {
        supported: true,
        reason: 'No discrete command, but Tab/Shift+Tab is an officially documented internal shortcut that indents/outdents a list item — only usable while the cursor is inside a list.',
    },
    multilevel_numbering: {
        supported: false,
        reason: 'Lists nest (indent) visually, but there is only one ListStyle applied uniformly at every level — no per-level numbering scheme (1 / 1.1 / 1.1.1, or 1 / a / i) exists.',
    },
    fullscreen: {
        supported: false,
        reason: 'Not a canvas-editor concept. Implemented at the application level via the browser Fullscreen API on the editor container.',
    },
    special_characters: {
        supported: true,
        reason: 'No dedicated "insert symbol" command, but inserting any character through command.executeInsertElementList([{ value: char }]) — the general text-insertion primitive — is a real, supported operation, not a fabricated one.',
    },
    page_layout_affects_pdf: {
        supported: false,
        reason: "Top/bottom Margins and the header/footer offset are honored by the generated PDF (they set the reserved header/footer band). Page size, orientation, left/right margins, background, page numbers, and columns apply live in the editor and are saved, but the generated PDF still renders through its own fixed A4 layout (dompdf) — those don't affect the PDF yet.",
    },
};
