# Fase 1X-N — Free/Pro packages and distribution checks

Version **2.0.37**. Branch `feature/phase-1x-n-free-pro-packages`.
Implementation and local tests complete; website approval and merge pending.
This is **not** a WordPress.org submission approval or commercial release.

## Packages and source deployment

The Git checkout remains **Pro**, for the existing Plesk deployment workflow.
Do not submit a GitHub source archive or the Pro package to WordPress.org.

Run `php tools/build-packages.php package/distributions` from a clean checkout.
The destination must not already exist; the builder never deletes prior builds.

| Artifact | Plugin folder | Contents |
| --- | --- | --- |
| `jellopoint-restaurant-menu.zip` | `jellopoint-restaurant-menu` | Free core, Multiple Prices, badges, labels, Builder, reusable Info Blocks, Elementor |
| `jellopoint-restaurant-menu-premium.zip` | `jellopoint-restaurant-menu-premium` | Shared core plus Daily/Weekly, Print/PDF and Import/Export |

Both packages contain the same main PHP filename, content types, options and
storage schema. The SDK gets a distinct edition flag and the explicit premium
folder name; its documented parallel-activation wrapper prevents double bootstrap.
The Free package does not contain the WordPress.org gatekeeper; Pro retains it.
Non-blocking expiry still retains features. Packaging does not change entitlement.

`tools/package-files.json` is an explicit allowlist. New runtime files fail the
build until reviewed. Development tools, tests, documents, Git/IDE metadata,
previous packages and secrets are not copied. The upstream SDK is retained with
its license and translations, excluding its Composer development manifest.

Named, non-nested `JPRM_PRO_BEGIN/END` regions identify embedded premium code.
The build removes those regions from Free and uses the small compatibility
replacements in `tools/free-regions.json`. These preserve Daily-menu warnings,
ordinary menu headings and a no-request Builder fallback. Pro retains the code.
Unknown, missing, nested, duplicated or malformed markers fail the build.
Whole Print/PDF, transfer and demo-import files are absent in Free. Daily controls,
scheduling, price/date output, separators and print-placement UI/REST/JS are removed.
The report records a SHA-256 for every packaged file; tests compare ZIP contents
with staging and the manifest. This guarantees content verification, not identical
ZIP timestamps between builds.

These are **our build markers**, not Freemius PHP-processor directives. Do not
publish Freemius's automatically generated Free ZIP from this Pro source. Only
the independently built and verified Free artifact is the Free release candidate.
Freemius deployment/download/update delivery is still a release-acceptance check.

## Data and onboarding

- Switching edition or license does not delete menu data. Existing Daily menus
  remain listed, show a preservation notice in their editor, and are hidden on
  the public frontend without Pro access. Authorized Elementor editors see why.
- Default badges, labels and icons install on activation and can now be restored
  under Settings in **both** editions, independently of Import/Export. The action
  checks administrator capability and a nonce and retains existing customization.
- Bundled icon URLs resolve against the active edition. Selected Media Library
  icons and third-party URLs are not rewritten by this resolver.
- The demo **import/removal workflow stays with Pro Import/Export**, as in 1X-M.
  Free does not contain a general importer disguised as onboarding. Free can
  create menus manually and retains already installed demo content.
- WPML's optional Daily/print metadata mappings remain for data preservation;
  they do not activate premium behavior or remove shared translations.
- Uninstall remains opt-in. For switching editions: **deactivate, do not delete**.

## Verification on 2026-09-07

| Check | Result |
| --- | --- |
| Standalone regression suite, including build compiler and defaults authorization | PASS — 30 test files |
| PHPCompatibilityWP, declared PHP 7.4–8.4 range | PASS — static compatibility scan |
| PHPStan using the locked toolchain | FIX — 21 findings, identical to the approved 8f399c7 baseline; no new findings |
| Built ZIP contents, allowlist, hashes, PHP syntax, edition identity and physical Pro exclusions | PASS — both editions |
| Builder request/loading lifecycle against each built JavaScript file | PASS — no Pro request in Free even with a forged UI flag |
| Actual WordPress 7.1 / PHP 8.2.12, isolated SQLite database | PASS — activation without Elementor, REST permissions, layouts, stored content |
| Actual Elementor 4.2.4 | PASS — widget registration/controls/rendering, Multiple Prices, price labels, badges and Daily editor-only warning |
| Free → Pro → Free using actual SDK bootstrap, without a license/network connection | PASS — fixture content, metadata and assignments unchanged |
| WordPress Plugin Check 2.1.0, static CLI checks on the Free artifact | FIX — 52 escaping errors and 252 warnings remain |

The local WordPress environment blocks external HTTP and uses no customer account,
license or restaurant data. An Elementor deprecation during its initial offline
API lookup was observed; subsequent smoke runs passed without that message.
These tests do not simulate a paid license, sandbox payment or update delivery.
Active and expired non-blocking licenses were website-approved in 1X-M, not
retested against Freemius online in this local run.

CI runs the built-package checks and Builder tests. A separate WordPress smoke
workflow uses a disposable MySQL 8 service and the current WordPress/Elementor
downloads to repeat the edition round trip. Its remote result must be checked
after pushing; the local database test used SQLite, not MySQL.

## Remaining distribution gate — do not skip before release

The exact static findings are stored in `phase-1x-n-plugin-check.json`.
The separate `phase-1x-n-phpstan.json` records the unchanged PHPStan findings
against an isolated copy of 8f399c7 with the same toolchain. These include
incomplete test doubles, Freemius symbol discovery and stale baseline patterns.
They also need correction before the blocking main-branch hardening job can pass;
the development workflow currently treats PHPStan as non-blocking. This phase
does not silence them or regenerate a permissive baseline.
The 52 errors are `WordPress.Security.EscapeOutput.OutputNotEscaped` signals in
shared admin/rendering paths. Some involve HTML-building helpers, but they have
**not** been dismissed as false positives: trace each value and either escape it
appropriately or justify a narrowly scoped safe-HTML exception with tests.

Warnings include unprefixed template variables (186), nonce-verification advice
on query parameters (42), sanitization, query performance/caching, and one
resource-version warning. Treat write paths separately from read-only filters.
New icon-parser warnings, the missing direct-access guard, missing translation
comments/literal, absent language directory and readme metadata issues were fixed.

Recommended next acceptance slice: review and resolve this report, run Plugin
Check with runtime checks on a served test site, confirm the WordPress.org
contributor account, then complete payment/privacy/API-failure/update tests in
1X-O. A green smoke test is not a WordPress.org approval.

## Website test through Plesk

1. Back up database and plugin files; retain the approved 2.0.36 rollback point.
2. Deploy `feature/phase-1x-n-free-pro-packages`; confirm **2.0.37** and Pro access.
3. Check the ordinary menu and Daily menu, Builder load/save, Multiple Prices,
   labels/badges, Info Blocks, Print/PDF including logo and Import/Export preview.
4. Open Settings. If testing restore defaults, verify custom rows remain intact.
   It adds missing defaults, so do this only on the test site.
5. Optionally repeat the approved license deactivate/reactivate checks. Expiry
   must continue to block only updates/support under the chosen non-blocking policy.

Plesk's source branch tests Pro, **not** the physically stripped Free package.
The package-switch acceptance should use a separate website clone when ready:
keep uninstall deletion OFF, deactivate the current edition, install the other
artifact and verify content before switching back. Do not extract the two ZIPs
into the same plugin folder or let Plesk overwrite the standalone Free package.
No ZIP installation is required for the first Plesk test above.

## Primary references

- [Freemius integration and parallel activation](https://freemius.com/help/documentation/wordpress-sdk/integration/integration-snippet/)
- [WordPress Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [WordPress.org plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
