# Phase 1X-L: Freemius sandbox integration (2.0.33)

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
JelloPoint and inspect the SDK connection/license screen. Use sandbox credentials
and licenses only; do not purchase through a production checkout. If no sandbox
license is available, stop and configure one in Freemius before proceeding.

## Acceptance checks

- SDK registration, Account screen and sandbox license activation/deactivation.
- Reload admin; existing menu entries and frontend remain available.
- Builder add/remove/save, Multiple Prices, badges and labels retain their data.
- Daily/Weekly, Print/PDF logo and output, Import/Export preview still work.
- Reject optional data sharing and verify the resulting SDK state.
- Deactivate/reactivate without losing restaurant data.
- Verify offline/API-failure behavior and updates in a real WordPress environment.

No module gating is introduced here. A premium build flag is not proof of a
license. Enforcement and the entitlement policy belong to 1X-M. Free/Pro split,
Free opt-out testing and independent packages remain 1X-N; do not publish this
combined build to WordPress.org. The gatekeeper marker must remain in this build.

Local regression tests do not replace the sandbox tests above. SDK network
behavior is outside the local-only checks of JelloPoint's own data code.
