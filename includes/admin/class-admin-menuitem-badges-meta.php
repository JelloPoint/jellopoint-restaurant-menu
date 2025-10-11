<?php
/**
 * JelloPoint Restaurant Menu – Admin: Menu Item > Dietary Badges Meta Box
 *
 * - Shows active badges from the store (ordered)
 * - Saves an array of slugs in post meta
 * - Renders in the NORMAL column (priority low) so we can place it under "Pricing"
 * - Zero dependencies on existing meta/JS
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

		// Scoped assets for this screen only
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
			'low'       // lower in the stack; we’ll fine-tune placement via JS
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $selected ) ) $selected = [];

		$rows   = $this->store->get_rows();
		$badges = [];

		foreach ( $rows as $r ) {
			if ( empty( $r['active'] ) ) continue;
			$name = isset( $r['name'] ) ? (string) $r['name'] : '';
			if ( $name === '' ) continue;
			$slug = sanitize_title( $name );
			$badges[] = [
				'slug'     => $slug,
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
			$slug     = $b['slug'];
			$name     = $b['name'];
			$icon_url = $b['icon_url'];
			$checked  = in_array( $slug, $selected, true );

			echo '<li class="jprm-badge-li">';
			echo '  <label class="jprm-badge-label">';
			if ( $icon_url ) {
				echo '    <img src="' . esc_url( $icon_url ) . '" alt="" class="jprm-badge-icon" />';
			} else {
				echo '    <span class="dashicons dashicons-format-image jprm-badge-placeholder" aria-hidden="true"></span>';
			}
			echo '    <span class="jprm-badge-name">' . esc_html( $name ) . '</span>';
			echo '    <input type="checkbox" name="jprm_item_badges[]" value="' . esc_attr( $slug ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '  </label>';
			echo '</li>';
		}
		echo '</ul>';

		echo '</div>';
	}

	public function save_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}
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
	 * Scoped CSS + JS:
	 * - Format badges nicely (horizontal chips)
	 * - Rename “Visibility & Badge” → “Visibility”
	 * - Hide the “Badge” text field in that box
	 * - Move our box directly under the “Pricing” meta box
	 */
	public function maybe_enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== self::POST_TYPE ) return;

		$h = 'jprm-badges-meta-inline';
		wp_register_style( $h, false ); wp_enqueue_style( $h );
		wp_add_inline_style( $h, $this->inline_css() );

		add_action( 'admin_print_footer_scripts', function () {
			?>
			<script>
			jQuery(function($){
				// 1) Rename the “Visibility & Badge” metabox heading to “Visibility”
				$('#poststuff .postbox h2, #poststuff .postbox h3').each(function(){
					var $h = $(this), t = $.trim($h.text());
					if (!t) return;
					var lower = t.toLowerCase();
					if (lower.indexOf('visibility & badge') !== -1) {
						$h.text('<?php echo esc_js( __( 'Visibility', 'jprm' ) ); ?>');
					}
				});

				// 2) Hide any single input row inside that same box where label equals “Badge”
				$('#poststuff .postbox').each(function(){
					var $box = $(this);
					var $head = $box.find('h2,h3').first();
					if (!$head.length) return;
					var lower = $.trim($head.text()).toLowerCase();
					// after rename it is "visibility"
					if (lower === 'visibility') {
						$box.find('tr, .form-field, .jprm-row').each(function(){
							var $row = $(this);
							var labelText = $.trim($row.find('label').first().text()).toLowerCase();
							if (labelText === 'badge') {
								$row.hide();
							}
						});
					}
				});

				// 3) Move our “Dietary Badges” box under the “Pricing” box in the MAIN column
				var $main = $('#post-body-content').length ? $('#post-body').find('#postbox-container-2') : $('#postbox-container-2');
				if ($main.length) {
					var $pricing = null;
					$main.find('.postbox h2, .postbox h3').each(function(){
						var t = $.trim($(this).text()).toLowerCase();
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
		/* Horizontal, compact chips */
		#jprm_menu_item_badges .inside { margin:0; padding:8px 12px; }
		.jprm-badges-meta-horizontal .jprm-badges-list {
			display:flex; flex-wrap:wrap; gap:8px; list-style:none; margin:0; padding:0;
		}
		.jprm-badges-meta-horizontal .jprm-badge-li {
			margin:0; padding:6px 8px; background:#fff; border:1px solid #dcdcde; border-radius:6px;
		}
		.jprm-badges-meta-horizontal .jprm-badge-label {
			display:flex; align-items:center; gap:8px; white-space:nowrap;
		}
		.jprm-badge-icon { width:18px; height:18px; object-fit:contain; border:1px solid #ccd0d4; border-radius:3px; background:#fff; }
		.jprm-badge-placeholder { color:#000; opacity:1; font-size:16px; }
		.jprm-badge-name { font-weight:500; }
		.jprm-badge-label input[type=checkbox] { margin-left:6px; }
		';
	}
}

endif;
