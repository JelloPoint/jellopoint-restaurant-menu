# 2.0.52 — Full-width section headings

Branch: `codex/section-title-columns` (based on develop at `2ef86f5`).

## Changes

- Content > Layout now follows Data Source; saved control IDs are unchanged.
- Optional "Section title above all columns" switch defaults off and appears for two or three desktop columns.
- When enabled, each section/subsection spans the menu width. Its items flow down the first column, then the next, balanced by rendered height without splitting items.
- Existing heading visibility, descriptions, section ordering, per-section label layouts and Info Blocks remain in use.
- Column count remains responsive; column gap applies to the new item columns. Manual section splitting is hidden/ignored only in the new mode.
- Matrix layout uses table formatting in the new desktop/tablet mode to retain aligned prices and repeat label headers across columns.
- No data migration, licensing changes or Free/Pro changes.

## Website acceptance

1. Open an existing Elementor widget: Layout should immediately follow Data Source. With the switch off, verify the previous layout and manual splits are unchanged.
2. Select two columns and enable the switch. Check long descriptions, badges and Multiple Prices stay with their items; each heading appears once across the full width.
3. Test three columns, column gap, tablet/mobile column selections, and switching back to one column.
4. Check Inline, Inline Below and Matrix, including per-section overrides and different device layouts. Check label headers in both Matrix columns.
5. Check nested sections, hidden titles/descriptions, sections without items, and Info Blocks above/below sections.
6. Check a Daily Menu with separators and a fixed price. Check multiple widgets with different settings on the same page.
7. Save/reload Elementor and compare the logged-out frontend. If an existing page shows cached styling, regenerate Elementor CSS and clear page cache.
8. Before distribution, process the Pro ZIP in Freemius and run Plugin Check against that generated Free ZIP.

Automated rendering tests also run against locally generated Free and Premium packages. Local browser fixtures use the actual templates/CSS but are not a replacement for the final Elementor website check.
