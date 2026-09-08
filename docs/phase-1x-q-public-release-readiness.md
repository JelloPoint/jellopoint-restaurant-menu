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
| 10 | EUR 89 | EUR 8.90 | 69.3% |
| 25 | EUR 119 | EUR 4.76 | 83.6% |

These are total prices per license tier, not per-site inputs. Monthly and
lifetime pricing remain out of scope unless explicitly added later. Freemius
`Release Plans` stays disabled while the matrix is configured and tested.
