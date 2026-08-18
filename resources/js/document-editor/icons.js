// Small inline-SVG icon set — no icon-font/library dependency. Each entry is raw path
// data for a 24x24 outline glyph; wrapped consistently by icon(). Bold/Italic/Underline/
// Strikethrough intentionally render as styled letters (B/I/U/S) rather than icons —
// that's how document editors have always represented them, including the one this
// toolbar takes its UX cues from.

const PATHS = {
    undo: 'M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
    redo: 'M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13',
    cut: 'M9.5 9.5 20 20M20 4 9.5 14.5M6 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm0 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',
    copy: 'M9 9h10v10H9zM5 15V5h10',
    paste: 'M9 4h6v3H9zM6 6h2v2h8V6h2v15H6z',
    pastePlain: 'M9 4h6v3H9zM6 6h2v2h8V6h2v15H6zM9 12h6M9 15h6M9 18h3',
    painter: 'M9 4h8v5l-2 2v2H9V9l-2-2V4h2Zm2 9v4M7 17h6v4H7a2 2 0 0 1-2-2v0a2 2 0 0 1 2-2Z',
    clearFormat: 'M4 5h11M6 5v11a2 2 0 0 0 2 2h4M14 5l-3 13M17 15l4 4m0-4-4 4',
    color: 'M6 20h12M8 16l4-11 4 11M9.2 13h5.6',
    highlight: 'M4 20h16M6 15l6-11 6 6-9 8H6z',
    alignLeft: 'M4 6h16M4 11h10M4 16h16M4 21h10',
    alignCenter: 'M4 6h16M7 11h10M4 16h16M7 21h10',
    alignRight: 'M4 6h16M10 11h10M4 16h16M10 21h10',
    justify: 'M4 6h16M4 11h16M4 16h16M4 21h16',
    lineSpacing: 'M7 3l3 3-3 3M10 6H4M7 21l-3-3 3-3M4 18h6M14 6h6M14 12h6M14 18h6',
    indentIncrease: 'M4 5h16M4 12h9M4 19h16M9 8l4 4-4 4',
    indentDecrease: 'M4 5h16M4 12h9M4 19h16M13 8l-4 4 4 4',
    bulletList: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
    numberedList: 'M9 6h11M9 12h11M9 18h11M4 6h1v3M4 9h2M4 13a1 1 0 0 1 2 0c0 .6-2 1.6-2 2.5h2M4 20l1-1v3',
    table: 'M4 5h16v14H4zM4 10h16M4 15h16M10 5v14',
    image: 'M4 5h16v14H4zM8 11a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM4 17l5-5 3 3 4-4 4 4',
    link: 'M9.5 14.5l5-5M8 16 5.5 18.5a3 3 0 0 1-4-4.5L4 11.5m12 1L18.5 10a3 3 0 0 0-4-4.5L12 8',
    hr: 'M4 12h16',
    pageBreak: 'M4 8h16M4 16h16M8 12h8m-2-2 2 2-2 2',
    specialChar: 'M6 4h12v6a6 6 0 0 1-12 0V4ZM4 20h16',
    headerFooter: 'M4 5h16M4 19h16M8 9h8M8 15h8',
    pageSetup: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M4.5 12a7.5 7.5 0 0 1 .3-2l-1.6-1.3 1.5-2.6 1.9.6a7.6 7.6 0 0 1 1.7-1l.3-2h3l.3 2a7.6 7.6 0 0 1 1.7 1l1.9-.6 1.5 2.6-1.6 1.3a7.5 7.5 0 0 1 0 4l1.6 1.3-1.5 2.6-1.9-.6a7.6 7.6 0 0 1-1.7 1l-.3 2h-3l-.3-2a7.6 7.6 0 0 1-1.7-1l-1.9.6-1.5-2.6 1.6-1.3a7.5 7.5 0 0 1-.3-2Z',
    find: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM21 21l-4.3-4.3',
    replace: 'M4 7h13l-3-3m3 3-3 3M20 17H7l3 3m-3-3 3-3',
    zoomIn: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM21 21l-4.3-4.3M8 11h6M11 8v6',
    fullscreen: 'M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5',
    more: 'M12 6h.01M12 12h.01M12 18h.01',
    chevronDown: 'M6 9l6 6 6-6',
    close: 'M6 6l12 12M18 6 6 18',
    check: 'M5 13l4 4L19 7',
    wordCount: 'M4 6h16M4 11h10M4 16h7',
};

export function iconMarkup(name, size = 18) {
    const d = PATHS[name];

    if (!d) {
        return '';
    }

    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">`
        + d.split(/(?=M)/).map((segment) => `<path d="${segment.trim()}" />`).join('')
        + '</svg>';
}

export function letterGlyph(letter, style) {
    return `<span aria-hidden="true" style="font-family:Georgia,serif;${style}">${letter}</span>`;
}
