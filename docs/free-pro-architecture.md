# Phase 1X-K — module boundaries (2.0.32)

This is the combined development distribution. All existing features remain
available. The catalog describes product ownership, not license entitlement.
There is deliberately no Free-mode switch until all embedded Pro paths have
been extracted and tested. This phase is not a standalone Free build.

| Module | Edition | Responsibilities |
| --- | --- | --- |
| menu_core | Free | Items, Menus, Sections, Builder, assignment service, badges, labels, Elementor base rendering |
| multiple_prices | Free | Price schemas, repository, editor and frontend multiple-price rendering |
| info_blocks | Shared Free core | Reusable content; Elementor usage; consumed by Pro printing |
| daily_weekly_menus | Pro | Dates, fixed daily price, scheduling, daily-specific controls and output |
| print_pdf | Pro | Print settings, document builder, rendering/templates and admin actions |
| import_export | Pro | CSV/JSON transfer UI and protected upload/download handlers |

`Module_Catalog` provides stable IDs, edition ownership and dependencies.
`Module_Loader` centralizes Print/PDF runtime loading and Print/PDF + Import/Export
admin bootstrapping. Repeated boot calls are idempotent. Core pricing and shared
content load independently in the main plugin. No license is inferred from the
presence of files or from the catalog.

## Extraction work required before a Free build

Daily functionality remains embedded in `class-jprm-menus-admin.php`, Elementor
controls/style traits, widget scheduling and display methods, and frontend menu
templates. Extract those paths together; disabling only the admin controls is
insufficient. Preserve existing metadata on edition changes.

Print consumes shared Info Blocks and optionally Daily data. It must remain
functional for ordinary Menus without a Daily module dependency. Print placement
controls and REST routes currently live in the shared Builder and need separation
from Free Info Block content management.

Demo data installation and default icons currently share the Import/Export admin
screen. Demo creation also calls the importer internally. Decide and extract the
Free onboarding path before excluding the Pro transfer module; never remove the
shared multiple-price schemas or assignment service with the importer.

The WPML XML also contains Daily and Print-related metadata. Packaging must keep
shared translations intact and account for the optional module fields.

## Follow-up phases

1. 1X-L: add a Freemius entitlement adapter, with actual product configuration.
2. 1X-M: enforce entitlement at module loading, admin actions, REST handlers,
   Elementor controls and rendering boundaries. The selected non-blocking plan
   keeps Pro features usable after expiry while Freemius stops updates/support.
3. 1X-N: extract remaining embedded paths and produce independent Free/Pro
   packages, including a Free package
   with Pro files physically absent. Run WordPress.org Plugin Check there.
4. 1X-O: verify activation, updates, license changes, data retention and releases.

No metadata migration, database cleanup, outbound request or license activation
is introduced by 1X-K.
