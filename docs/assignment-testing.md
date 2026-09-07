# Assignment regression testing — 2.0.30

An item has zero or one Section per Menu, and may belong to several Menus.
The Menu structure owns placements. WordPress taxonomy terms reflect the union
of the item's actual Menu and Section placements.

The item editor displays one Section selector per Menu, read from the same
structures as Builder. Saving an unchanged selector preserves item order.
Not assigned removes only that Menu's placement. Menus not included in the form
are left unchanged, including Menus outside the current language view.

## Website acceptance

1. Assign an unassigned item to a Section in Menu A from its editor. Reopen the
   editor, Builder and frontend; all three must agree.
2. Save again without changing the assignment. Its order must stay unchanged.
3. Move it to another Section in Builder; reopen the editor and check the new
   Section. Save there and verify the move persists.
4. Remove it from Builder; reopen the editor, confirm Not assigned, and save.
   The item must not reappear in Builder or on the frontend.
5. Assign the same item to A and B using a shared Section. Remove it from A.
   B must retain its assignment and the item must still display there.
6. Remove its final assignment; both Menu and Section taxonomy terms must clear.
7. Detach a Section in Builder and verify its items lose only that Menu placement.
8. Check Menu Items list filters and Menu column for an item in multiple Menus.
9. Export/import an unchanged item, then import an unambiguous Section move.
   Check Builder, editor and frontend, plus prices, labels and badges.
10. Dry-run an ambiguous CSV row with two Sections in one Menu. It must report a
    skipped row instead of guessing. Automatic term creation requires a single
    Menu and Section; set up more complex shared structures in Builder first.
11. Repeat the editor/Builder round trip for translated content with WPML.

Unscoped Section bulk assignment and taxonomy quick edit are no longer offered;
use Builder's batch assignment within a selected Menu. No database-wide repair
is performed on activation. Existing structures are retained, and item saves
reconcile taxonomy assignments for that item.

Automated coverage uses the real Editor save handler, Builder controller,
structure store and assignment service with in-memory WordPress functions.
It does not replace the website and WPML acceptance checks above.
