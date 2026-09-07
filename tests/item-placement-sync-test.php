<?php
/** Round trips through real Editor, Builder controller, structure store and taxonomy projection. */
define( 'ABSPATH', __DIR__ );
$meta = [
	8 => [ 'version' => 1, 'sections' => [ [ 'id' => 11, 'parent_id' => 0, 'order' => 0, 'items' => [] ], [ 'id' => 12, 'parent_id' => 0, 'order' => 1, 'items' => [] ] ] ],
	9 => [ 'version' => 1, 'sections' => [ [ 'id' => 11, 'parent_id' => 0, 'order' => 0, 'items' => [] ] ] ],
];
$terms = []; $hooks = []; $valid_nonce = true; $can_edit = true; $term_writes = 0;
class WP_REST_Controller { public $namespace; public $rest_base; }
class WP_Error {}
function add_action( $name, $callback, $priority = 10, $args = 1 ) { global $hooks; $hooks[$name][] = [$callback, $args]; }
function add_filter( ...$args ) { add_action( ...$args ); }
function do_action( $name, ...$args ) { global $hooks; foreach ( $hooks[$name] ?? [] as $hook ) { call_user_func_array( $hook[0], array_slice( $args, 0, $hook[1] ) ); } }
function get_post_type( $id ) { return 'jprm_menu_item'; }
function wp_get_post_terms( $id, $taxonomy, $args = [] ) { global $terms; return $terms[$id][$taxonomy] ?? []; }
function wp_set_post_terms( $id, $ids, $taxonomy, $append = false ) { global $terms, $term_writes; $term_writes++; $terms[$id][$taxonomy] = $append ? array_values(array_unique(array_merge($terms[$id][$taxonomy] ?? [], $ids))) : $ids; return $ids; }
function get_terms( $args ) { return [8, 9]; }
function term_exists( $id, $taxonomy ) { return in_array( $id, [8,9], true ); }
function get_term( $id, $taxonomy ) { return (object) ['term_id' => $id, 'taxonomy' => $taxonomy]; }
function get_term_by( $field, $name, $taxonomy ) {
	$map = ['jprm_menu' => ['A' => 8, 'B' => 9], 'jprm_section' => ['S' => 11, 'T' => 12]];
	return isset($map[$taxonomy][$name]) ? (object) ['term_id' => $map[$taxonomy][$name]] : false;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_term_meta( $id, $key, $single ) { global $meta; return $meta[$id] ?? ''; }
function update_term_meta( $id, $key, $value ) { global $meta; if (($meta[$id] ?? null) === $value) { return false; } $meta[$id] = $value; return true; }
function wp_is_post_revision( $id ) { return false; }
function wp_is_post_autosave( $id ) { return false; }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce( $value, $action ) { global $valid_nonce; return $valid_nonce && 'valid' === $value; }
function current_user_can( ...$args ) { global $can_edit; return $can_edit; }
function get_taxonomy( $name ) { return (object) ['cap' => (object) ['assign_terms' => 'edit_posts', 'edit_terms' => 'manage_categories']]; }
function rest_ensure_response( $value ) { return $value; }
function __( $value, $domain = '' ) { return $value; }
require_once dirname(__DIR__) . '/includes/data/class-menu-structure-store.php';
require_once dirname(__DIR__) . '/includes/data/class-item-assignments.php';
require_once dirname(__DIR__) . '/includes/admin/class-item-placement-sync.php';
require_once dirname(__DIR__) . '/includes/rest/class-jprm-menu-builder-controller.php';
use JelloPoint\RestaurantMenu\Data\Menu_Structure_Store as Store;
use JelloPoint\RestaurantMenu\Data\Item_Assignments as Assignments;
use JelloPoint\RestaurantMenu\Admin\Item_Placement_Sync as Editor;
Assignments::init();
$builder = new JelloPoint\RestaurantMenu\REST\Menu_Builder_Controller();
function check($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
function edit_item($id, $pairs) {
	$_POST = ['jprm_placements_nonce' => 'valid', 'jprm_placements' => $pairs];
	Editor::save($id, (object) ['post_type' => 'jprm_menu_item']);
}
function assert_terms($id, $menus, $sections) {
	$actual_menus = wp_get_post_terms($id, 'jprm_menu');
	$actual_sections = wp_get_post_terms($id, 'jprm_section');
	sort($actual_menus); sort($actual_sections); sort($menus); sort($sections);
	check($menus === $actual_menus && $sections === $actual_sections, 'Taxonomy projection mismatch for item ' . $id);
}
edit_item(100, [8 => 11, 9 => 11]);
assert_terms(100, [8,9], [11]);
check(11 === Store::item_placements(8)[100]['section_id'], 'Editor did not assign to Builder.');
edit_item(101, [8 => 11]);
$before = $meta;
$writes_before = $term_writes;
edit_item(100, [8 => 11, 9 => 11]);
check($before === $meta, 'Unchanged editor save reordered items.');
check($writes_before === $term_writes, 'Unchanged assignments triggered taxonomy writes.');
Store::save(8, Store::get(8));
check($writes_before === $term_writes, 'Unchanged Builder save triggered taxonomy writes.');
$builder->unassign_item(['menu_id' => 8, 'id' => 100]);
assert_terms(100, [9], [11]);
check(!isset(Store::item_placements(8)[100]), 'Builder removal failed.');
edit_item(100, [8 => 0, 9 => 11]);
check(!isset(Store::item_placements(8)[100]), 'Reopening and saving resurrected removed placement.');
$builder->unassign_item(['menu_id' => 9, 'id' => 100]);
assert_terms(100, [], []);
edit_item(100, [8 => 0, 9 => 0]);
assert_terms(100, [], []);
$builder->assign_items_batch(['menu_id' => 8, 'section_id' => 11, 'ids' => [100]]);
assert_terms(100, [8], [11]);
$builder->save_items_order(['menu_id' => 8, 'items' => [['id' => 100, 'section_id' => 12, 'order' => 0]]]);
assert_terms(100, [8], [12]);
assert_terms(101, [], []);
edit_item(100, [8 => 11, 9 => 11]);
assert_terms(100, [8,9], [11]);
Store::detach_section(8, 11);
assert_terms(100, [9], [11]);
$before = $meta; $before_terms = $terms;
edit_item(100, [9 => 0, 8 => 999]);
check($before === $meta && $before_terms === $terms, 'Invalid pair partially applied.');
$valid_nonce = false;
edit_item(100, [9 => 0]);
check($before === $meta, 'Invalid nonce changed placements.');
$valid_nonce = true; $can_edit = false;
edit_item(100, [9 => 0]);
check($before === $meta, 'Unauthorized save changed placements.');
$can_edit = true;
$_POST = [];
Editor::save(100, (object) ['post_type' => 'jprm_menu_item']);
check($before === $meta, 'Missing editor controls removed assignments.');
edit_item(100, [9 => [11,12]]);
check($before === $meta, 'Multiple Sections were accepted in one Menu.');
echo "Item assignment round-trip checks passed.\n";
// Import planning must use explicit pairs, and must not mutate a dry run.
Store::attach_section(8, 11);
$before = $meta; $before_terms = $terms;
$plan = Assignments::plan_names(100, ['A', 'B'], ['S']);
check([9 => 11, 8 => 11] === $plan, 'Shared Section import did not map to both Menus.');
check($before === $meta && $before_terms === $terms, 'Import planning wrote data.');
check(false === Assignments::plan_names(200, ['A'], ['S', 'T']), 'Ambiguous CSV assignment was accepted.');
check(Assignments::replace(100, $plan), 'Import plan failed.');
assert_terms(100, [8,9], [11]);
check(Assignments::replace(100, Assignments::plan_names(100, ['A'], ['T'])), 'Imported move failed.');
assert_terms(100, [8], [12]);
check(Assignments::replace(100, Assignments::plan_names(100, [], [])), 'Imported removal failed.');
assert_terms(100, [], []);
echo "Import assignment planning checks passed.\n";
