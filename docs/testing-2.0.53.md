# 2.0.53 development validation — user-test candidate

Base: clean `develop`, commit `0d2d9e5`, version 2.0.52.
Local branch: `codex/matrix-balance-item-visibility`.
User subsequently requested minimal further testing and delivery of the branch for website testing. Public release remains on hold; no merge or publication is authorized.

## Implemented

- Content > Item Title & Description immediately follows Sections and Menus.
- Independent title/description switches default to On; absent settings preserve content.
- Visibility is passed to Inline, Inline Below and Matrix, including device variants.
- Hidden titles do not hide dietary badges or leave dotted leaders. Hidden descriptions do not leave empty description wrappers/grid rows.
- Prices-only Matrix removes the item-text minimum width; prices, labels and badges remain.
- Version header, runtime constant, readme and version assertions are 2.0.53.
- No stored content, labels, licensing or edition boundaries were changed.

## Matrix balancing: targeted candidate, visual confirmation pending

The live filtered-menu page was inspected, not modified. Its Wine section has a desktop Matrix variant and an Inline tablet/mobile variant. At desktop widths of approximately 1280 and 1536 pixels, the available Chromium browser places Merlot and Prosecco in column one and Sauvignon Blanc in column two, with repeated headers. The reported left-column-only failure did not reproduce.

The standalone fixture now represents the supplied export's configuration: Inline globally, Wine section override Matrix, forced Inline on smaller devices, three Wine items, four distinct case-sensitive price labels and the observed Matrix minimum item width. It preserves distinct labels rather than attempting to repair stored data.

In Chromium, the fixture balances in two and three columns, repeats headers, and keeps long-description item rows intact. Tablet switches to Inline/two columns; mobile switches to Inline/one column. These are observations of the previous balancing implementation, **not evidence of a fix**.

Following the user's request to minimize further testing and provide a branch, a targeted candidate moves the column formatting context onto each responsive variant instead of its outer wrapper. The Matrix is now a direct child of the active column container, while section headings remain outside. Identical-device layouts and the switch-Off markup are unchanged. A focused regression checks this structure and legacy mode. The original browser-specific failure still requires user confirmation; Firefox has not been tested. Earlier browser/package results below precede this final wrapper change; the final change receives focused PHP lint and rendering regression checks only.

## Validation

- `php tests/run.php`: all 34 test files pass, including the real WordPress KSES icon-filter test using the existing local WordPress 6.5 helper files.
- `composer cs`: passes the configured check (warnings disabled by project configuration).
- `composer compat`: passes the existing PHP 7.4–8.4 compatibility scan; this is a static check, not execution on every PHP version.
- `composer stan`: fails with 56 findings. These include incomplete Elementor stubs, baseline-count mismatches, WordPress/test-stub conflicts and missing SDK symbols. New calls to `get_settings` and controls add to the incomplete-stub diagnostics. No new suppressions or baseline edits were made.
- An isolated HEAD comparison could not complete: PHPStan exhausted memory at both 1 GB and 2 GB. Consequently, an exact before/after finding count is not asserted.
- Both local Free/Pro archives pass manifest, archive checksum, PHP syntax, SDK identity and physical module-separation checks.
- The expanded section/visibility regression test passes against source and both built editions: all four visibility combinations in all layouts and mixed variants, badges On/Off, retained prices/labels/Info Blocks and absent-setting defaults.
- Browser checks at desktop and 390px mobile: all four visibility combinations in all three layouts, no page overflow; mixed layout also checked at 820px tablet.
- Actual local WordPress + Elementor 4.2.4 rendering passes editor/frontend mode checks for both built editions, all three layouts and four visibility combinations. Controls/defaults, dietary badges and a single price were checked. The existing QA multi-price item was supplied a single price through a request-only metadata filter for this test; stored data was not edited. Pro license activation was not retested.
- This was not an interactive Elementor save/publish test on the demo website, nor an official Plugin Check run on a newly Freemius-generated ZIP.

## Plesk/demo test steps

1. Back up the demo plugin files/database. Once the feature branch is pushed, select it in Plesk Git, pull/deploy, and confirm version 2.0.53.
2. Open Elementor > Content. Confirm Item Title & Description follows Sections and Menus. Existing widgets should show both by default.
3. In Inline, Inline Below and Matrix, try both On, title Off, description Off, and both Off. Check badges, prices and labels remain. Also test an item with no badge. Publish and inspect the frontend after clearing relevant caches.
4. Test desktop Matrix with Inline on smaller devices, two/three columns, long descriptions, Info Blocks and the section-heading switch both On and Off.
5. Compare the original Wine issue in the affected browser and Chromium. Report browser/version, viewport width and whether Ctrl+F5 changes the result. Do not regard Matrix as fixed until the original failure is reproduced and resolved.

Do not upload these builds to Freemius/WordPress.org or treat 2.0.53 as a public release.
