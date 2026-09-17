# E-PRISM research studio

The web interface adapts the modular cards, pill actions, themed form fields, badges, and navigation patterns in [Astryx](https://astryx.atmeta.com/), especially its [theme explorer](https://astryx.atmeta.com/themes) and [component catalog](https://astryx.atmeta.com/components).

These are original Blade/CSS implementations inspired by those patterns. Astryx's React/StyleX package is not installed. Existing Alpine behavior and Laravel routes remain the integration points.

## Color roles

| Role | Color | Use |
| --- | --- | --- |
| Primary | Red `#bc202c` | Main actions, active navigation, brand panel |
| Highlight | Yellow `#ffdc58` | Discovery card, navigation accents, secondary actions |
| Attention | Pale yellow | Revision metrics, priority banner, filters |
| Review | Lavender | Review metrics and knowledge card |
| Approval | Green | Approved metrics and completed research badges |
| Canvas | Warm white `#f6f3ec` | Page background |

Statuses keep visible labels and icons so color is not the only signal. Yellow surfaces use dark text. Keyboard focus is visible, native form controls retain their behavior, and press motion respects reduced-motion preferences.

## Integration

- `resources/css/astryx-ui.css`: scoped web component styles, imported by `app.css`.
- `welcome.blade.php`: research process cards, authenticated/guest actions, live submission windows and memorandum links.
- Shared layouts: navigation, authentication, page headings, and focused editor/review shells.
- Shared components: action links, buttons, filter panels, repository cards, metrics, and modal surfaces.
- Application tables opt in through `research-table`; manuscript/PDF templates do not.

The previous Uiverse theme is superseded. Its attribution file is retained as historical provenance.

## DepEd and Santiago identity

The identity strip uses the project's existing DepEd and Schools Division of Santiago City images at their original proportions and colors. A white backing improves their clarity. Blue `#16458d` adds an education-oriented accent alongside the primary red and yellow.

Research did not establish an authoritative municipal palette or exact Santiago City brand hex values. These interface colors are a design interpretation of the existing division seal and education identity, not an official city color specification. The division seal in this project visibly combines red, yellow, blue, and butterfly imagery.

The original `balamban-butterfly` Blade component combines butterfly wings with an open book. It is decorative artwork, not a replacement for an official seal. It appears in the landing card, a local-inspiration note, authentication artwork, and dashboard priority labels. No continuous animation is used.

Sources consulted:

- [DepEd Service Marks and Visual Identity Manual, DO 031 s. 2019](https://www.deped.gov.ph/wp-content/uploads/2019/11/DO_s2019_031.pdf): primary identity reference.
- [Province of Isabela — Festivals](https://provinceofisabela.gov.ph/tourism/festival/): links Santiago's Balamban festival to butterfly movements, thanksgiving, and transformation.
- [Santiago City Tourism Development Plan 2023–2025](https://cityofsantiago.gov.ph/wp-content/uploads/2025/10/R11thCC-112-Adopting-and-Approving-the-2023-2025-Tourism-Development-Plan-of-the-City-of-Santiago.pdf): identifies Balamban as the Dance of the Butterfly.

The association between butterfly growth and research discovery is our design interpretation of this local connection.

## Motion

- The landing butterfly greets visitors with two 900ms wing movements once at least 60% of it enters the viewport. It then rests; dashboard and authentication butterflies stay still.
- Action arrows move 2px on mouse hover. Buttons give 140ms press feedback.
- Filter panels enter in 180ms and exit in 125ms, using opacity and a 4px translation. Alpine retains control of visibility and interruption.
- Keyboard actions skip these transitions. Reduced-motion mode removes the flutter and movement, retaining only a short filter fade. Preferences changed during the visit are respected.
- These effects use CSS and a small IntersectionObserver helper, with no additional dependency. Content remains visible if animation APIs are unavailable.
