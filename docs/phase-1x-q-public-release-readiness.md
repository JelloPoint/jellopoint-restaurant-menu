# Fase 1X-Q — public release readiness

Version **2.0.40**. Branch
`feature/phase-1x-q-public-release-readiness`.

This phase prepares and verifies the final Free and Pro release artifacts without
making either edition public or accepting a production payment.

## Safety boundary

- Keep Freemius `Release Plans` disabled.
- Keep the existing 2.0.39 deployment in Beta status until 2.0.40 is verified.
- Do not add Freemius development mode, email-activation bypasses or any secret
  key to source, Git, a package or the test website.
- Freemius pricing may be edited while plans remain unreleased. Record and
  verify the final single-site and bulk annual EUR pricing in a later sandbox
  checkout; pricing configuration is not stored in this repository.
- Do not deploy the source branch over the Freemius-managed premium plugin on
  the existing updater test website. Use isolated package/runtime checks first.
- Do not submit a package to WordPress.org during this phase.

## Acceptance scope

1. Repeat optional data-sharing rejection and confirm the Free edition remains
   usable without opt-in.
2. Simulate an unavailable Freemius API and confirm existing restaurant data and
   frontend output remain available without PHP or JavaScript errors.
3. Build independent Free and Pro ZIPs from the same revision.
4. Verify the Free package physically excludes Daily/Weekly Menus, Print/PDF and
   Import/Export while retaining Multiple Prices.
5. Verify version, folder slugs, SDK identity, licensing, uninstall lifecycle,
   file manifests, PHP syntax and package checksums.
6. Run the complete regression, Builder, compatibility, coding-standard and
   static-analysis checks.
7. Run the current WordPress Plugin Check against the actual Free artifact and
   review every remaining warning.
8. Scan source and both packages for development constants, secret keys and
   unintended build files.
9. Confirm the intended WordPress.org contributor account and the current
   `Tested up to` value before the later submission phase.

## Intended module split

- Pro: Daily/Weekly Menus, Print/PDF and Import/Export.
- Free: Multiple Prices and all previously approved core features.

Any finding that changes runtime code requires normal automated checks and a
separate website acceptance before merging. Passing this phase does not itself
authorize `Release Plans`, a production checkout, a normal Freemius release or
a WordPress.org submission.

## Intended annual EUR pricing

The following Pro pricing matrix was selected for later sandbox verification:

| Activations | Annual total | Effective price per site | Discount versus separate single-site licenses |
| ---: | ---: | ---: | ---: |
| 1 | EUR 29 | EUR 29.00 | — |
| 3 | EUR 49 | EUR 16.33 | 43.7% |
| 10 | EUR 99 | EUR 9.90 | 65.9% |
| 25 | EUR 149 | EUR 5.96 | 79.4% |

These are total prices per license tier, not per-site inputs. Monthly and
lifetime pricing remain out of scope unless explicitly added later. Freemius
`Release Plans` stays disabled while the matrix is configured and tested.

## Automated and isolated acceptance

Verified on 8 September 2026:

| Check | Result |
| --- | --- |
| 30 standalone regression test files | PASS |
| Builder lifecycle in source, Free and Pro | PASS |
| WordPress coding standards | PASS |
| PHP 7.4–8.4 compatibility | PASS |
| PHPStan | PASS against accepted baseline; the same 21 recorded findings and no new finding |
| Free/Pro manifest, ZIP, checksums, PHP syntax and SDK identity | PASS |
| Physical Pro-module exclusion from Free; Multiple Prices retained | PASS |
| WordPress 7.1 / Elementor 4.2.4 actual Free and Pro runtime | PASS |
| Free → Pro data retention and frontend/Builder/Elementor smoke tests | PASS |
| Free anonymous opt-out state | PASS; tracking prohibited and core functionality retained |
| Freemius API unavailable simulation | PASS; external HTTP blocked and core/frontend functionality retained |
| Secret/development setting and unwanted build-file scan | PASS |
| Plugin Check 2.1.0 static and runtime-enabled checks on Free | PASS; 0 errors and the same 58 reviewed warnings |

The 58 Plugin Check warnings remain the reviewed set from 1X-O: read-only admin
filter parameters without mutation nonces, intentional taxonomy/meta queries,
custom term ordering and scoped uninstall transient cleanup. No new warning or
error was introduced in 2.0.40.

## Candidate artifacts

| Edition | Files | SHA-256 |
| --- | ---: | --- |
| Free `jellopoint-restaurant-menu.zip` | 264 | `8B9D79D27BD2BAE47BBC8D004A55A3AA565AC11FB4EAD777E445B7DE46EC4769` |
| Pro `jellopoint-restaurant-menu-premium.zip` | 277 | `EE34FBF3612D697C17B91A74C3FD7AD5B43E089FCC39BA9E5B59AA77CC782640` |

The checks used the current stable WordPress 7.1 release and current Plugin
Check 2.1.0. The Free artifact remains the only candidate eligible for the later
WordPress.org submission; the Pro artifact is only for Freemius processing.

## Remaining manual acceptance

1. Confirm the exact WordPress.org contributor username.
2. Keep `Release Plans` disabled and do not publish a normal deployment.

## Freemius processing acceptance

The four annual EUR tiers were visible in the hosted sandbox checkout. No
additional transaction was made. Freemius then processed the verified Pro
artifact as 2.0.40; its generated Paid ZIP has SHA-256:

`7232AD973D04EE7708B8311CACA7DCB4938647462A363B007EED1EC5F0B37988`

The source Pro directory contained 277 files and the generated Paid directory
276 files. Exactly two expected processing differences were found:

1. Freemius transformed the main plugin file, adding its Update URI and live
   deployment state, normalizing the integration snippet and wrapping runtime
   bootstrap in its parallel-edition guard.
2. Freemius removed `vendor/freemius/README.md`, which is not a runtime file.

All other 275 files were byte-identical. The processed package retained version
2.0.40, the premium identity, the uninstall hook class and no root
`uninstall.php`; it contained no secret key. All 205 PHP files passed syntax,
the packaged Builder test passed, and the actual WordPress 7.1 / Elementor 4.2.4
Pro smoke test passed with data retained.

## Website Beta update acceptance

The audited 2.0.40 deployment was changed from Unreleased to Beta. After the
test installation rejoined the Freemius Beta Program, WordPress offered and
installed the real 2.0.39 to 2.0.40 premium update.

The website retained exactly one active JelloPoint plugin, the Beta label, the
original active test license and all existing restaurant data. Ordinary menu
frontend output, Elementor preview, Daily Menu, Print/PDF and Import/Export were
manually confirmed operational. No Plesk/source deployment was used.
