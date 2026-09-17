{{--
    Typography shared by the real document (template-shell.blade.php) and the isolated
    header/footer measurement pass (measure-shell.blade.php, via PdfContentHeightMeasurer).
    Both need to agree byte-for-byte — anything that changes how tall header/footer content
    renders belongs here, not duplicated in either caller, or the measured height and the
    actual rendered height will quietly drift apart again (the same class of bug as the
    header/footer geometry mismatch this measurement exists to prevent).

    Deliberately excluded: position/top/bottom/height/overflow and the img max-height cap
    on header/footer images — those are geometry-dependent (computed FROM the measured
    height), so applying them during measurement would be circular.
--}}
<style>
    {{--
        canvas-editor only ever emits line-height on a per-run <span> when a row's
        margin explicitly differs from the document default — the common case (using the
        default) exports none at all, so without a baseline here dompdf falls back to its
        own font_height_ratio, unrelated to canvas-editor's row-height math, and normal
        content renders visibly tighter/looser than the editor showed it. This doesn't
        achieve pixel-perfect parity for every per-run override (those stay span-level, a
        different level than canvas-editor's own row-level model) but closes the gap for
        the common default case.
    --}}
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; }
    p { margin: 0 0 8px 0; }
    strong { color: #0f172a; }
    {{--
        table-layout: fixed (not the default auto) is what actually keeps a many-column
        research-tables chapter (e.g. a 12-week work plan) within the page: under auto
        layout, a column never shrinks below its content's own longest unbreakable run, so
        enough columns simply overflow past the page's right edge — cut off entirely, not
        just visually cramped, since nothing continues them onto a following page either.
        Fixed layout distributes the declared 100% width evenly across columns instead, and
        word-wrap/overflow-wrap is what makes a long unbroken value (a word, a number) still
        wrap inside its now-narrow column rather than forcing the row wider regardless.
    --}}
    table { width: 100%; border-collapse: collapse; margin: 8px 0; table-layout: fixed; }
    table td, table th { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9px; text-align: left; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
    {{--
        Every research-tables chapter (work plan, cost estimates, dissemination plan, etc.)
        puts its one free-text label column first and short values (a week's "X", a peso
        amount) after — without this, fixed layout's equal-width default squeezes that label
        column exactly as narrow as a one-character "X" column, which reads fine for the
        short columns but forces every word in the label column to hyphen-break mid-word.
    --}}
    table td:first-child, table th:first-child { width: 22%; }
    thead { display: table-header-group; }
    ul, ol { margin: 0 0 8px 0; padding-left: 20px; }
    img { max-width: 100%; }
    header p { margin: 0; font-size: 10px; color: #334155; }
    header strong { font-size: 12px; color: #b30f35; letter-spacing: 0.5px; }
    footer { font-size: 8px; color: #94a3b8; }
    footer p { margin: 0; }
    header, footer { text-align: center; padding: 4px 0; box-sizing: border-box; }
</style>
