# Phase 1X-O — commercial release acceptance

Version **2.0.38**. Branch
`feature/phase-1x-o-commercial-release-acceptance`.

This phase closes the blocking WordPress.org static findings from 1X-N and
prepares the existing Free and Pro artifacts for final website acceptance. It
does not release a plan, process a payment or publish a deployment.

## Automated acceptance

| Check | Result |
| --- | --- |
| 30 standalone regression test files | PASS |
| Builder loading lifecycle | PASS |
| PHP 7.4–8.4 compatibility scan | PASS |
| Separate Free and Pro package compiler | PASS |
| Plugin Check 2.1.0 static checks, Free package | PASS — 0 errors |
| Plugin Check 2.1.0 runtime-enabled CLI checks, Free package | PASS — 0 errors and no runtime-only finding |
| PHPStan | PASS against the accepted baseline — no new finding; 21 existing findings remain recorded from 1X-N |

Plugin Check still reports **58 reviewed warnings**:

- 42 nonce recommendations concern sanitized, read-only admin URL parameters
  used for filters, sorting and success/warning notices. These requests do not
  change state; all state-changing handlers retain capability and nonce checks.
- 12 performance recommendations concern the intentional taxonomy/meta queries
  that resolve menu relationships and admin filters. Replacing those queries is
  a future performance task, not a release security gate.
- Four database recommendations describe two deliberate direct query sites:
  custom admin term ordering and wildcard transient cleanup during an explicitly
  opted-in uninstall. Both are scoped and prepared where values are dynamic.

## Changes reviewed

- Admin label and menu-assignment attributes now use contextual escaping.
- Stored label previews and rich Info Block content are constrained at output.
- Price, badge and layout renderers explicitly document their already-escaped
  assembled HTML, while preserving SVG-mask and responsive markup.
- WordPress.org translations use core just-in-time language-pack loading; no
  bundled translations currently require manual loading.
- The Free package still physically excludes Daily/Weekly Menus, Print/PDF and
  Import/Export. Multiple Prices remains present in Free.
- Freemius `Release Plans` remains OFF and no secret key is stored in source or
  either package.

## Website acceptance for 2.0.38

1. Deploy the branch through Plesk and confirm version **2.0.38**.
2. Open Price Labels: confirm existing labels/icons, add or edit one row, save,
   reload and confirm that no unrelated labels changed.
3. Edit one Menu Item with a single price and one with Multiple Prices. Confirm
   Price Labels, icons and Dietary Badges remain selected after saving.
4. Check the same menu on the frontend in Inline, Inline Below and Matrix
   layouts. Confirm prices, SVG/raster icons, badges, descriptions and Info
   Blocks render normally.
5. Edit Menu assignments on the item and confirm the Builder and frontend agree.
6. Open the Elementor editor and confirm the selected menu renders in preview.
7. Confirm Daily/Weekly, Print/PDF and Import/Export remain available with the
   active Pro license and Multiple Prices remains available.

## External acceptance still required before going live

- Complete one Freemius sandbox checkout and activation using a sandbox license.
- Repeat optional data-sharing rejection and simulated API-unavailable behavior.
- Validate delivery of an actual non-public update to an entitled installation
  and absence of that update/support entitlement when appropriate.
- Confirm the WordPress.org contributor account and perform the final submitted
  Free ZIP review against the then-current WordPress and Plugin Check versions.
- Only after explicit approval: release the Freemius plan/deployment. Do not turn
  on `Release Plans` during the website test above.

Authoritative references:

- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://wordpress.org/plugins/plugin-check/
- https://freemius.com/help/documentation/wordpress/sdk/testing/
- https://freemius.com/help/documentation/wordpress/deployment-process/
