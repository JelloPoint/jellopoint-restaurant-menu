# Fase 1X-P — private Freemius release candidate

Version **2.0.39**. Branch
`feature/phase-1x-p-freemius-release-candidate`.

This phase validates delivery of an actual premium update through Freemius. It
does not publish the Free package, release the paid plan, or process a live
payment. `Release Plans` stays disabled.

## Safety boundary

- Keep the Plesk test website on approved `develop` version **2.0.38** before
  creating the Freemius deployment. Do not deploy this branch through Plesk;
  the update from 2.0.38 to 2.0.39 must arrive through Freemius.
- Upload only the locally built and verified **Pro** ZIP to Freemius.
- The package has no root `uninstall.php`. Freemius owns the WordPress uninstall
  hook and invokes JelloPoint's cleanup through `after_uninstall`. Cleanup still
  requires the existing explicit setting; its default remains OFF.
- Mark 2.0.39 as a **Beta Release**, not as a normal public release. The test
  installation must explicitly join the Freemius Beta Program.
- Freemius may display an automatically generated Free download. Do not publish,
  submit or distribute that file. Only JelloPoint's independently built and
  verified Free artifact is eligible for later WordPress.org review.
- Keep a database and files backup. Updating must not delete or rewrite menu
  content, assignments, prices, labels, badges, settings or license state.
- Never add the Freemius secret key or development constants to source, a ZIP,
  Git, chat or screenshots. They belong only in the test site's `wp-config.php`.

## Candidate artifacts

Run the audited package builder in a new destination. It produces:

| Use | Artifact |
| --- | --- |
| WordPress.org candidate, not used in this updater test | `jellopoint-restaurant-menu.zip` |
| Freemius beta deployment upload | `jellopoint-restaurant-menu-premium.zip` |

The package report and tests must confirm the shared SDK identity, the premium
folder slug, PHP syntax, checksums and physical exclusion of Pro modules from
Free. The regression, Builder, compatibility, static-analysis and Plugin Check
gates from 1X-O remain required.

## Automated acceptance

| Check | Result |
| --- | --- |
| 30 standalone regression test files | PASS |
| Builder loading lifecycle, source and both packages | PASS |
| PHP 7.4–8.4 compatibility and coding standards | PASS |
| PHPStan against the accepted baseline | PASS — no new finding |
| Free/Pro manifests, ZIP contents, checksums and PHP syntax | PASS |
| Freemius uninstall package rule and opt-in cleanup lifecycle | PASS |
| Local WordPress 7.1 / Elementor 4.2.4 Free → Pro → Free | PASS — content retained |
| WordPress Plugin Check 2.1.0 on Free | PASS — 0 errors; 58 reviewed warnings |

## Manual beta-update acceptance

1. Confirm the test website still runs **2.0.38**, has the Pro edition active,
   and has a valid activated test license.
2. In Freemius open **Deployment**, add the verified Pro ZIP as version 2.0.39,
   and release it only as a **Beta Release**.
3. In WordPress open **JelloPoint → Account** and join the **Beta Program**.
4. Open **Dashboard → Updates** and trigger **Check again**, or use
   the update action on **JelloPoint → Account** when it appears.
5. Before installing, confirm the offered version is 2.0.39 and the plugin name
   is JelloPoint Restaurant Menu Pro. Install the update through WordPress.
6. Confirm version 2.0.39, active license state and all existing restaurant data.
7. Test an ordinary Menu and Daily Menu, Elementor preview/frontend, Builder
   load/save, Multiple Prices, labels/badges, Info Blocks, Print/PDF and
   Import/Export preview. Confirm the corrected Labels Layout and Info Blocks
   Section dropdowns follow the selected Menu hierarchy.
8. Leave the Beta Program after acceptance unless continued beta updates are
   intentionally wanted.

## Acceptance result

Approved on 8 September 2026.

- Freemius accepted and processed the verified Pro package as version 2.0.39.
- The Freemius-generated Paid ZIP was compared with the source Pro package;
  only the expected SDK/deployment processing changes were present.
- The generated Paid ZIP passed PHP syntax and local WordPress/Elementor Pro
  activation, functional and Free rollback checks with data retained.
- The test website received 2.0.39 through the Freemius Beta Program from the
  existing 2.0.38 installation.
- WordPress showed one active JelloPoint plugin after the update, marked Beta;
  no duplicate Free or Premium installation was created.
- The active license, existing restaurant data, ordinary and Daily menus,
  Elementor, Builder, labels/badges, Print/PDF and Import/Export remained
  operational after the update.

Phase 1X-P is approved. The deployment remains a Beta Release and `Release
Plans` remains disabled; neither the paid plan nor a public release is enabled
by this acceptance.

## Sandbox checkout acceptance

The hosted Freemius sandbox checkout was approved on 8 September 2026:

- The checkout URL was explicitly in sandbox mode and showed the intended Pro,
  single-site, annual EUR 39 purchase.
- Freemius processed the test payment and created the expected sandbox payment,
  annual subscription and one-site license; the license email was received.
- The purchased sandbox license activated successfully on the test website and
  retained access to the Pro modules. Freemius counted it as one production
  activation because the public test-domain name is not classified as a local or
  staging hostname; this did not turn the sandbox transaction into a live sale.
- The sandbox license was deactivated after acceptance. The temporary Freemius
  development-mode, email-activation bypass and product secret-key constants
  were removed from `wp-config.php`.
- After leaving sandbox mode, Freemius Debug was no longer active and the
  original test license was successfully restored with Pro access intact.

The updater, checkout, payment, subscription and purchased-license activation
flow are therefore accepted. Optional data-sharing rejection/API-unavailable
rechecks and any public launch remain separate release decisions. `Release Plans`
and normal public deployment must stay disabled until explicitly approved.
WordPress.org submission remains a separate final step using the independently
built Free ZIP.
