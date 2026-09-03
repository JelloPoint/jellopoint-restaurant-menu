# JelloPoint Restaurant Menu – Roadmap

This is the central overview of completed, active, and planned development phases.
Each phase is tested on the JPRM development website before it is merged into `develop`.

## Status key

- ✅ Approved and merged into `develop`
- 🚧 In progress
- ⏳ Planned
- 🗂️ Later / scope still to be refined

## Completed foundation and hardening

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Plugin bootstrap and admin mutation hardening | ✅ Approved |
| 2 | Bootstrap consolidation and compatibility cleanup | ✅ Approved |
| 3 | Price data and schema stabilization | ✅ Approved |
| 4 | Import/export hardening and reliable dry-run reporting | ✅ Approved |
| 5 | Elementor widget query and fallback hardening; Dynamic mode only | ✅ Approved |
| 6 | Admin data and Dietary Badges hardening | ✅ Approved |
| 7 | Release readiness, automated checks, and GitHub quality gates | ✅ Approved |
| 8 | Safe demo menu import and removal | ✅ Approved |
| 9 | Elementor Design Presets: Classic, Modern, and Elegant | ✅ Approved |
| 9B | Default Dietary Badges and Price Labels with bundled icons | ✅ Approved |

## Daily and weekly menus

| Phase | Scope | Status |
| --- | --- | --- |
| 10A | Daily Menu metadata: menu type, no date/single date/date range, and fixed price | ✅ Approved |
| 10B | Elementor Daily Menu output, price positioning, item separators, styling, and full-width fixed-price presentation | ✅ Approved |
| 10C | Automatically show or hide Daily/Weekly Menus according to their date or date range | ✅ Approved |
| 10D | Shared Sections across multiple Menus, with independent items and ordering per Menu, plus Builder section selection | ✅ Approved |

### Phase 10C intended behaviour

- `No Date`: always available.
- `Single Date`: available only on that date.
- `Date Range`: available from the start date through the end date, inclusive.
- Use the WordPress site timezone rather than the visitor's device timezone.
- Provide a clear Elementor setting for automatic date filtering.
- Keep editor preview and frontend behaviour understandable when a menu is currently outside its active period.
- Never delete or modify menu content when a menu is outside its active period.

### Phase 10D intended behaviour

- Allow one Section definition to be attached to multiple Menus.
- Store the Menu → Section relationship independently from the Section taxonomy term itself.
- Allow each Menu to have its own Menu Items and item order inside the same shared Section.
- Preserve the section order and hierarchy independently for each Menu.
- In Menu Builder, **Add Section** offers two actions:
  - select and attach an existing Section;
  - create and attach a new Section.
- Prevent accidental duplicate attachments within the same Menu.
- Removing a Section from one Menu must not delete the Section or affect another Menu.
- Existing single-owner Section data remains readable and is migrated safely when edited.
- Elementor output and queries must use the selected Menu's own Section contents.
- Import/export must preserve the new per-Menu relationships.

## Printable menus and PDF export

The website display remains powered by Elementor. Print and PDF use dedicated templates optimized for paper.

| Phase | Scope | Status |
| --- | --- | --- |
| 11A | Print/PDF foundation: document settings, paper size, orientation, margins, and reusable data pipeline | ✅ Approved |
| 11B | Dedicated printable templates and print presets | ⏳ Planned |
| 11C | PDF generation, download, and browser-print workflow | ⏳ Planned |

### Print/PDF requirements

- Build documents from existing Menus, Sections, Menu Items, prices, Dietary Badges, Price Labels, and their icons.
- Do not create a second independent menu-content system.
- Allow the creator to select an existing menu and choose a print-specific template.
- Support at least A4 initially, with portrait and landscape orientation.
- Produce a clean print version without website navigation or Elementor page decoration.
- Preserve readable typography and sensible page breaks.
- Make PDF output suitable for printing, hanging in the restaurant, or distributing as a menu.
- Keep print/PDF styling separate from Elementor website styling while reusing the same content.

## Additional planned improvements

| Area | Scope | Status |
| --- | --- | --- |
| Elementor Atomic | Add compatibility with Elementor V4 Atomic styling without breaking the current widget | 🗂️ Later |
| WPML | Explicit multilingual handling for menus, sections, relationships, and stored Elementor IDs | 🗂️ Later |

## Phase workflow

1. Create a feature branch from the approved `develop` branch.
2. Implement and run automated checks.
3. Push the feature branch to GitHub.
4. Deploy that branch to the development website through Plesk.
5. Complete the agreed browser, Elementor, admin, and data tests.
6. Merge into `develop` only after explicit approval.
7. Update this roadmap when scope or status changes.
