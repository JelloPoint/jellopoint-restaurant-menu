=== JelloPoint Restaurant Menu ===
Tags: restaurant, menu, elementor, food, prices
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 2.0.34
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create restaurant menus with sections, multiple prices, dietary badges and an Elementor widget.

== Description ==

Manage Menu Items, Menus, Sections and reusable Info Blocks centrally. Display your restaurant menu using the JelloPoint Restaurant Menu Elementor widget.

Features include single and multiple prices, price labels, dietary badges, icons, section ordering and responsive presentation controls.

This development distribution also contains Daily/Weekly Menus, Print/PDF and CSV Import/Export. These are intended Pro modules; the separate Free and Pro distributions are still in preparation. Multiple Prices remains a Free feature.

Elementor is required for the website widget. Menu management remains available without Elementor. WPML is optional; an included language configuration registers menu content and widget fields for translation.

== External service: Freemius ==

This Pro development build bundles Freemius SDK 2.13.4 for account registration, license activation, updates, checkout and optional usage-data sharing. The SDK communicates with Freemius services and can transmit site URL, plugin and WordPress/PHP versions, account/contact details and license information as part of these operations. Review its connection and permission screens before proceeding. Restaurant menu content is not explicitly sent by JelloPoint to Freemius.

Service: https://freemius.com/
Terms: https://freemius.com/terms/
Privacy: https://freemius.com/privacy/

This is not a WordPress.org-ready Free package. Pro-module access enforcement follows in a later phase. See docs/freemius-testing.md in the source repository before deploying this development build.

== Installation ==

1. Install and activate JelloPoint Restaurant Menu.
2. Activate Elementor to display menus on your website.
3. Create your content under JelloPoint in the WordPress administration.
4. Add the Restaurant Menu widget to an Elementor page and select a Menu.

== Frequently Asked Questions ==

= Does deactivation or deletion remove restaurant data? =

Deactivation retains data. Deleting the plugin retains data by default. Enable the explicit delete-data option under JelloPoint Settings only if you want plugin data removed during uninstall.

= How is a PDF created? =

Open the printable menu and use your browser's print dialog to save as PDF.

== Changelog ==

= 2.0.34 =
* Gate Daily/Weekly, Print/PDF and Import/Export entry points behind Pro entitlement.
* Keep Multiple Prices and stored restaurant data available independently of license status.

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
