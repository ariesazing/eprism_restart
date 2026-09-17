# Research notebook UI

Historical note: this theme was superseded by the Astryx-inspired research studio. See `astryx-ui.md` for the active implementation. The original source attribution and license are retained below.

The web layouts opt into `research-ui`; `resources/css/research-ui.css` supplies the shared treatment for dashboards, repository, administration, submission forms, authentication, and focused editor/review shells. PDF templates do not opt in.

## Uiverse sources

- [gharsh11032000 — loud-chicken-53](https://uiverse.io/gharsh11032000/loud-chicken-53): adapted into `components/research-action.blade.php`. Original sliding arrows and circular hover fill, with cherry colors, semantic links, transform-based fill, shorter transitions, keyboard focus, and reduced-motion support.
- [andrew-demchenk0 — green-deer-16](https://uiverse.io/andrew-demchenk0/green-deer-16): adapted outlined input styling. Retains existing visible form labels and fluid field widths rather than adding floating placeholder labels.

Source retrieved from the corresponding `Buttons/` and `Inputs/` HTML files in [Uiverse Galaxy](https://github.com/uiverse-io/galaxy). Notebook surfaces, metric colors, navigation, table styling, and badges are local companion styles.

## MIT License

Copyright (c) 2023 Uiverse.io

Component attribution: gharsh11032000 and andrew-demchenk0.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
