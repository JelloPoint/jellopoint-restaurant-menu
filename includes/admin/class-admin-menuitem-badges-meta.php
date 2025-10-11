<?php
/**
 * JelloPoint Restaurant Menu – Admin: Menu Item > Dietary Badges Meta Box
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'JPRM_MenuItem_Badges_Meta' ) ) :

class JPRM_MenuItem_Badges_Meta {

	const POST_TYPE = 'jprm_menu_item';
	const META_KEY  = 'jprm_item_badges_v1';

	const NONCE_NAME   = 'jprm_item_badges_nonce';
	const NONCE_ACTION = 'jprm_item_badges_save';

	/** @var JPRM_Badges_Store */
	protected $store;

	public function __construct( $store_instance ) {
		$this->store = $store_instance;

		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ], 20 );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );

		// Scoped assets and DOM tweaks for the edit screen only.
		add_action( 'load-post.php',     [ $this, 'maybe_enqueue_assets' ] );
		add_action( 'load-post-new.php', [ $this, 'maybe_enqueue_assets' ] );
	}

	public function register_metabox() {
		add_meta_box(
			'jprm_menu_item_badges',
			__( 'Dietary Badges', 'jprm' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'normal',   // main column
			'low'       // place lower; we will insert under Pricing via JS
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $selected ) ) $selected = [];

		$badges = [];
		foreach ( $this->store->get_rows() as $r ) {
			if ( empty( $r['active'] ) ) continue;
			$name = isset( $r['name'] ) ? (string) $r['name'] : '';
			if ( $name === '' ) continue;
			$badges[] = [
				'slug'     => sanitize_title( $name ),
				'name'     => $name,
				'icon_url' => isset( $r['icon_url'] ) ? (string) $r['icon_url'] : '',
			];
		}

		echo '<div class="jprm-badges-meta jprm-badges-meta-horizontal">';
		if ( empty( $badges ) ) {
			echo '<p>' . esc_html__( 'No active badges found. Configure them under “Dietary Badges”.', 'jprm' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<ul class="jprm-badges-list">';
		foreach ( $badges as $b ) {
			$checked = in_array( $b['slug'], $selected, true );
			echo '<li class="jprm-badge-li">';
			echo '  <label class="jprm-badge-label">';
			if ( $b['icon_url'] ) {
				echo '    <img src="' . esc_url( $b['icon_url'] ) . '" alt="" class="jprm-badge-icon" />';
			} else {
				echo '    <span class="dashicons dashicons-format-image jprm-badge-placeholder" aria-hidden="true"></span>';
			}
			echo '    <span class="jprm-badge-name">' . esc_html( $b['name'] ) . '</span>';
			echo '    <input type="checkbox" name="jprm_item_badges[]" value="' . esc_attr( $b['slug'] ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '  </label>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	public function save_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( self::POST_TYPE !== $post->post_type ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$input = isset( $_POST['jprm_item_badges'] ) ? (array) $_POST['jprm_item_badges'] : [];
		$clean = [];

		$valid = [];
		foreach ( $this->store->get_rows() as $r ) {
			if ( ! empty( $r['active'] ) && ! empty( $r['name'] ) ) {
				$valid[] = sanitize_title( (string) $r['name'] );
			}
		}
		foreach ( $input as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( in_array( $slug, $valid, true ) ) $clean[] = $slug;
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, array_values( array_unique( $clean ) ) );
		}
	}

	/**
	 * CSS + robust DOM tweaks:
	 * - Rename "Visibility & Badge" -> "Visibility"
	 * - Hide the "Badge" row (label = Badge OR input name/id contains 'badge')
	 * - Move our box under "Pricing" (this already worked)
	 * - Improve horizontal layout
	 */
	public function maybe_enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== self::POST_TYPE ) return;

		// Styles: stronger specificity + a few !important to beat theme/admin styles if needed.
		$h = 'jprm-badges-meta-inline';
		wp_register_style( $h, false ); wp_enqueue_style( $h );
		wp_add_inline_style( $h, $this->inline_css() );

		add_action( 'admin_print_footer_scripts', function () {
			?>
			<script>
			jQuery(function($){
				// Helper: get clean lowercased text for a heading node (strips child elements)
				function headingText($h){
					var clone = $h.clone();
					clone.find('*').remove();
					return $.trim(clone.text()).toLowerCase();
				}

				// 1) Find the "Visibility & Badge" box by heading (case-insensitive, tolerant)
				var $visBox = null;
				$('#poststuff .postbox').each(function(){
					var $box = $(this);
					var $h = $box.find('h2.hndle, h3.hndle, h2, h3').first();
					if (!$h.length) return;
					var txt = headingText($h);
					// match if contains both words in any order
					if ( txt.indexOf('visibility') !== -1 && txt.indexOf('badge') !== -1 ) {
						$visBox = $box;
						// Rename heading to "Visibility"
						$h.contents().filter(function(){ return this.nodeType === 3; }).remove();
						$h.text('<?php echo esc_js( __( 'Visibility', 'jprm' ) ); ?>');
						return false;
					}
				});

				// 2) Inside that box, hide the "Badge" field robustly
				if ($visBox && $visBox.length) {
					// a) table rows like: <tr><th><label>Badge</label>...</tr>
					$visBox.find('tr').each(function(){
						var $row = $(this);
						var label = $.trim($row.find('th label, td label, label').first().text()).toLowerCase();
						if (label === 'badge') {
							$row.hide();
						}
					});
					// b) .form-field blocks
					$visBox.find('.form-field').each(function(){
						var $row = $(this);
						var label = $.trim($row.find('label').first().text()).toLowerCase();
						if (label === 'badge') {
							$row.hide();
						}
					});
					// c) Any lone inputs with name/id containing "badge" -> hide their closest row-ish container
					$visBox.find('input, select, textarea').each(function(){
						var $el = $(this);
						var name = ($el.attr('name') || '').toLowerCase();
						var id   = ($el.attr('id') || '').toLowerCase();
						if (name.indexOf('badge') !== -1 || id.indexOf('badge') !== -1) {
							$el.closest('tr, .form-field, p, div').first().hide();
						}
					});
				}

				// 3) Move our “Dietary Badges” box under the “Pricing” box (you said this already works)
				var $main = $('#postbox-container-2'); // main column
				if ($main.length) {
					var $pricing = null;
					$main.find('.postbox h2.hndle, .postbox h3.hndle, .postbox h2, .postbox h3').each(function(){
						var t = headingText($(this));
						if (t === 'pricing' || t.indexOf('price') !== -1) {
							$pricing = $(this).closest('.postbox');
							return false;
						}
					});
					var $badges = $('#jprm_menu_item_badges');
					if ($pricing && $badges.length) {
						$badges.insertAfter($pricing);
					}
				}
			});
			</script>
			<?php
		});
	}

	protected function inline_css() : string {
		return '
		/* Stronger specificity + !important to ensure consistent look */
		#jprm_menu_item_badges .inside { margin:0 !important; padding:8px 12px !important; }
		#jprm_menu_item_badges .jprm-badges-meta-horizontal .jprm-badges-list {
			display:flex !important; flex-wrap:wrap !important; gap:8px !important;
			list-style:none !important; margin:0 !important; padding:0 !important;
		}
		#jprm_menu_item_badges .jprm-badge-li {
			margin:0 !important; padding:6px 8px !important;
			background:#fff !important; border:1px solid #dcdcde !important; border-radius:6px !important;
		}
		#jprm_menu_item_badges .jprm-badge-label {
			display:flex !important; align-items:center !important; gap:8px !important; white-space:nowrap !important;
		}
		#jprm_menu_item_badges .jprm-badge-icon { width:18px !important; height:18px !important; object-fit:contain !important; border:1px solid #ccd0d4 !important; border-radius:3px !important; background:#fff !important; }
		#jprm_menu_item_badges .jprm-badge-placeholder { color:#000 !important; opacity:1 !important; font-size:16px !important; }
		#jprm_menu_item_badges .jprm-badge-name { font-weight:500 !important; }
		#jprm_menu_item_badges input[type=checkbox] { margin-left:6px !important; }
		';
	}
}

endif;
