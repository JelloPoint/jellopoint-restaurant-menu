# JelloPoint Restaurant Menu – Dev Checklist

**Goal**: Add features safely (no regressions) and defer refactors.

## When starting a change
- [ ] Create a git branch from the current working tag (e.g. `v1-baseline`).
- [ ] Ensure `tests/fixtures/labels_option.json` mirrors the site’s `jprm_price_labels_v2`.
- [ ] Keep two Elementor test pages:
  - [ ] Dynamic page (no menus/sections selected). Toggle "Fallback to all items".
  - [ ] Filtered page (specific menu + section).

## After each change (quick sanity)
- [ ] Elementor editor opens with **no PHP warnings** (WP debug log empty).
- [ ] Dynamic page renders:
  - [ ] With fallback OFF and no filters → empty notice shown.
  - [ ] With fallback ON and no filters → items render.
- [ ] Filtered page renders only the selected terms.
- [ ] Labels:
  - [ ] `pl-*` rows show icons/text per configuration.
  - [ ] Custom label row shows its image icon if `icon_id` is set.
- [ ] Alignment unchanged vs. previous commit.
- [ ] Copy the `<ul class="jp-menu">…</ul>` and diff against `tests/fixtures/expected_widget_markup.html`.

## Rules during feature work
- [ ] Only **add** new controls or meta; do not rename/remove existing keys.
- [ ] Do not change DOM classnames or structure unless intentional (then update snapshot).
- [ ] Do not alter `jprm_price` or `jprm_price_labels_v2` shapes during build.

## Before release
- [ ] PHPCS (WordPress ruleset) – fix docblocks/spacing only.
- [ ] i18n: all strings use `jellopoint-restaurant-menu`.
- [ ] Confirm widget icon, categories, keywords are correct.
