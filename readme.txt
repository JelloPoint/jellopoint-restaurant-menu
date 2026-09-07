=== JelloPoint Restaurant Menu ===
Tags: restaurant, menu, elementor, food, prices
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 2.0.30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create restaurant menus with sections, multiple prices, dietary badges and an Elementor widget.

== Description ==

Manage Menu Items, Menus, Sections and reusable Info Blocks centrally. Display your restaurant menu using the JelloPoint Restaurant Menu Elementor widget.

Features include single and multiple prices, price labels, dietary badges, icons, section ordering and responsive presentation controls.

This development distribution also contains Daily/Weekly Menus, Print/PDF and CSV Import/Export. These are intended Pro modules; the separate Free and Pro distributions are still in preparation. Multiple Prices remains a Free feature.

Elementor is required for the website widget. Menu management remains available without Elementor. WPML is optional; an included language configuration registers menu content and widget fields for translation.

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
