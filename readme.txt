=== JelloPoint – Restaurant Menu ===
Tags: restaurant, menu, elementor, food, prices
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.53
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create beautiful, flexible restaurant menus in WordPress with sections, prices, dietary badges and seamless Elementor integration.

== Description ==

**Create beautiful restaurant menus directly in WordPress.**

JelloPoint – Restaurant Menu gives restaurants, cafés and food businesses an easy way to create, organize and display professional menus on their website.

Build your menu from reusable **Menus, Sections, Menu Items and Info Blocks**, then display it with the included Elementor widget. Organize dishes in the Menu Builder, add prices and dietary information, and customize the presentation to match your website.

[View the live demo](https://demo.jellopoint.com/) to explore example restaurant menus. The demo also showcases Pro features; see the Free and Pro feature lists below for availability.

= Free features =

* Create and manage multiple restaurant menus
* Reusable Sections and Menu Items
* Visual Menu Builder for organizing your menu
* Single prices and customizable price labels
* Dietary badges and icons
* Reusable Info Blocks
* Elementor Restaurant Menu widget
* Flexible menu layouts and extensive styling controls
* Responsive presentation
* Demo menu import to help you get started
* WPML configuration for multilingual menu content

= JelloPoint Pro =

JelloPoint Pro adds:

* Multiple Prices – ideal for sizes, portions, glass/bottle pricing and more
* Daily and Weekly Menus
* Print-friendly menus and PDF creation
* CSV and JSON Import/Export

If a Pro subscription expires, Pro functionality continues to work with the installed version. Renewal is required for future Pro updates and support.

= Elementor integration =

Use the JelloPoint Restaurant Menu widget to place your menus directly in Elementor. Choose the menu you want to display and customize its layout, typography, spacing, colors, sections, prices, badges and other presentation options from the Elementor editor.

Elementor is required for the website widget. Restaurant menu management in WordPress remains available without Elementor.

= External service: Freemius =

The plugin bundles Freemius SDK 2.13.4 for account registration, license activation, updates, checkout and optional usage-data sharing. The SDK communicates with Freemius services and can transmit site URL, plugin and WordPress/PHP versions, account/contact details and license information as part of these operations. Review its connection and permission screens before proceeding. Free menu management does not require a license or optional usage-data sharing. Restaurant menu content is not explicitly sent by JelloPoint to Freemius.

Service: https://freemius.com/
Terms: https://freemius.com/terms/
Privacy: https://freemius.com/privacy/

Product information: https://jellopoint.com/

== Installation ==

1. Install and activate JelloPoint Restaurant Menu.
2. Activate Elementor to display menus on your website.
3. Create your content under JelloPoint in the WordPress administration.
4. Add the Restaurant Menu widget to an Elementor page and select a Menu.

== Frequently Asked Questions ==

= Does deactivation or deletion remove restaurant data? =

Deactivation retains data. Deleting the plugin retains data by default. Enable the explicit delete-data option under JelloPoint Settings only if you want plugin data removed during uninstall.

= How is a PDF created? =

In Pro, open the printable menu and use your browser's print dialog to save as PDF.

== Screenshots ==

1. Build and organize restaurant menus with reusable Sections and Menu Items.
2. Display and customize your restaurant menu visually with the JelloPoint Elementor widget.
3. Create clean restaurant menu layouts with prices, descriptions and flexible styling.
4. Add dietary information, icons and price labels directly to your menu items.
5. Create structured drinks menus with flexible pricing layouts.

== Changelog ==

= 2.0.53 =
* Balance item columns inside each responsive layout variant when full-width section headings are enabled.
* Add independent Elementor controls for item titles and descriptions, preserving prices and dietary badges in all layouts.

= 2.0.52 =
* Move Elementor Layout controls directly below Data Source.
* Add optional full-width section headings with automatically balanced item columns.

= 2.0.51 =
* Show full annual prices on the Freemius Upgrade page instead of monthly equivalents.
* Fix Matrix price column alignment so label icons and headers follow the price cell alignment.
* Add responsive Price Column Spacing under Style > Prices & Labels for Matrix layouts, without extra padding after the last price column.

= 2.0.50 =
* Fix Price Labels form fields disappearing and existing labels being lost when saving.

= 2.0.49 =
* Preserve dietary badge and price-label SVG masks through WordPress output filtering.

= 2.0.48 =
* Harden Menu Builder REST reads and complete-placement saves for non-public items.
* Escape generated menu, badge, label, Info Block, and price HTML at output boundaries.

= 2.0.47 =
* Keep justified read-only admin filter exceptions detectable after Freemius Free-package processing.

= 2.0.46 =
* Make output escaping and nonce-scope annotations stable after Freemius preprocessing.
* Complete the remaining WordPress Plugin Check internationalization and input-sanitization fixes.

= 2.0.45 =
* Prepare a clean Freemius upload package that excludes development-only resources.
* Address the remaining actionable WordPress Plugin Check findings in runtime code.
* Declare complete Print/PDF and Import/Export files as premium-only for Freemius processing.

= 2.0.44 =
* Move Multiple Prices to Pro while keeping single prices fully available in Free.
* Preserve stored Multiple Prices data across Free/Pro edition or license changes.
* Add Freemius-compatible premium code markers for the Multiple Prices implementation.

= 2.0.43 =
* Address WordPress.org review feedback for request sanitization, term-save authorization and scoped admin notices.
* Load plugin admin scripts and styles through the WordPress enqueue APIs.

= 2.0.42 =
* Prevent Freemius from adding a second Pro suffix to the neutral plugin name; Premium builds remain identified by the PRO badge.
* Explain when an inactive Free companion remains installed after upgrading to the Premium edition.

= 2.0.41 =
* Use one neutral plugin name for both editions and label only the installed Premium build with a PRO badge on the Plugins screen.

= 2.0.40 =
* Prepare final public-release Free and Pro artifacts and launch-safety acceptance.

= 2.0.39 =
* Provide an audited Free/Pro release candidate for private Freemius beta update testing.
* Keep Elementor Section Override and Info Block Position choices scoped to the selected Menu's saved hierarchy.
* Run optional data cleanup through Freemius' uninstall lifecycle while preserving the default data-retention policy.

= 2.0.38 =
* Resolve all blocking WordPress Plugin Check findings in the Free distribution.
* Harden contextual escaping for menu layouts, labels, prices, Info Blocks and admin assignment fields.
* Use WordPress just-in-time translation loading for WordPress.org language packs.

= 2.0.37 =
* Build separate Free and Pro distributions with Pro implementations physically excluded from Free.
* Safely handle activation when both editions are installed and preserve existing content.
* Make missing default badges, price labels and icons restorable under Settings in both editions.

= 2.0.36 =
* Explain retained Daily/Weekly menus without Pro access in the menu editor and authorized Elementor preview, without displaying notices to website visitors.

= 2.0.35 =
* Keep the Free Builder loading by skipping Pro-only Print/PDF placement requests without entitlement.

= 2.0.34 =
* Gate Daily/Weekly, Print/PDF and Import/Export entry points behind Pro entitlement.
* Keep stored restaurant data available independently of license status.

= 2.0.33 =
* Integrate the official Freemius SDK and product configuration for sandbox testing.
* Include the SDK in Git deployments and built packages; document external service and license notices.

= 2.0.32 =
* Introduce a central Free/Pro module catalog and module bootstrap.
* Keep all features enabled in the combined development distribution pending license integration and separate packaging.

= 2.0.31 =
* Load independent Builder data requests concurrently.
* Show loading/saving feedback and block duplicate interaction while requests are pending.
* Avoid rewriting unchanged taxonomy assignments and index placements once per save.

= 2.0.30 =
* Use explicit Menu-to-Section pairs in the item editor, with one Section per Menu.
* Synchronize Builder additions, moves, removals and Section detachments with item taxonomy assignments.
* Route import assignments through the shared placement service and reject ambiguous rows.
* Use Menu Builder for batch assignments; remove the unscoped Section bulk actions and taxonomy quick-edit controls.

= 2.0.29 =
* Synchronize item-editor Menu and Section selections with Menu Builder placements.
* Preserve valid placement order and warn when multiple Sections cannot be paired with a Menu.

= 2.0.28 =
* Exclude development files and the build staging directory from distribution packages.
* Add installation and distribution documentation.

= 2.0.27 =
* Add explicit WPML language configuration.
