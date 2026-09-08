# Phase 1X-L: Freemius sandbox integration (2.0.33)

For current separate-edition packaging and local round-trip results, see
[Phase 1X-N](phase-1x-n-packages.md). This page retains the earlier website
approvals and the online license/payment checks still needed before release.

Product 39068; Pro plan `pro`; initial offer EUR 39 annually for one site.
The user-supplied dashboard snippet initializes bundled SDK 2.13.4 early in the
main plugin. No secret or sandbox constant is shipped. Release Plans remains off.
The SDK is tracked for Plesk Git deployment; Composer development tools are not.

## Before deploying on the test website

Back up the database and files. In the test site's wp-config.php only, before
WordPress loads, add the three development constants shown in the Freemius SDK
Integration dashboard step 8: WP_FS__DEV_MODE, WP_FS__SKIP_EMAIL_ACTIVATION and
the product-specific secret-key constant. Use the newly rotated secret. Never
paste it into chat, plugin files, Git or a public screenshot. Do not enable these
settings on customer/production websites. Remove them before production use.

Deploy the feature branch using Plesk Git. Confirm version 2.0.33, then open
JelloPoint and inspect the SDK connection/license screen. A manually created
dashboard license can be used for activation testing without a purchase. This
does not verify sandbox checkout or prove the license is a sandbox entity.
For payment testing, use an explicitly identified sandbox checkout only.

## Website approval (2026-09-07)

The user confirmed activation and the requested functional checks worked, then
approved merging 1X-L into develop. Activation used a manually created dashboard
license, not a sandbox purchase. Release Plans remains off. Sandbox payments,
license expiration/deactivation, optional data-sharing rejection, API failure
and update behavior still require acceptance testing before commercial release.

## Acceptance checks

### Phase 1X-M website approval

The user approved v2.0.36 after testing active Pro access, license deactivation,
Free Builder behavior, Daily Menu notices in the admin and Elementor preview,
and restoration after reactivating the same license. The test license still had
"Block feature access when license expires" enabled despite the plan setting;
the user was directed to disable it on that license, then confirmed the expiry
test worked and approved the merge into develop. Pro features must remain usable
after non-blocking expiry. Restoring the test license's future expiry date was
requested but has not been explicitly confirmed.

Sandbox checkout/payment, optional data-sharing rejection, API failure and actual
update delivery/restriction remain release acceptance items. The earlier 1X-L
pending list above records the status at that phase, not the completed 1X-M tests.

- SDK registration, Account screen and sandbox license activation/deactivation.
- Reload admin; existing menu entries and frontend remain available.
- Builder add/remove/save, Multiple Prices, badges and labels retain their data.
- Daily/Weekly, Print/PDF logo and output, Import/Export preview still work.
- Reject optional data sharing and verify the resulting SDK state.
- Deactivate/reactivate without losing restaurant data.
- Verify offline/API-failure behavior and updates in a real WordPress environment.

1X-M adds module gating. A premium build flag is not proof of a license. The
non-blocking plan deliberately keeps features available after expiry while
updates and support are withheld by Freemius. Free/Pro split,
Free opt-out testing and independent packages remain 1X-N; do not publish this
combined build to WordPress.org. The gatekeeper marker must remain in this build.

Local regression tests do not replace the sandbox tests above. SDK network
behavior is outside the local-only checks of JelloPoint's own data code.
