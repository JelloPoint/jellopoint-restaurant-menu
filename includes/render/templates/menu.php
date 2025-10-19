<?php
/**
 * Unified Menu Template (1/2/3 columns)
 * Expects $ctx (array) with keys used below.
 * Provides three rendering helpers (guarded) and dispatches by $ctx['columns'].
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* --- Safe helper for menu meta (if not already defined by widget) --- */
if ( ! function_exists( 'jprm_render_menu_meta' ) ) {
	function jprm_render_menu_meta( $term, bool $show_title, bool $show_desc, string $scope ) : string {
		if ( ! $term || ( ! $show_title && ! $show_desc ) ) return '';
		$title = $show_title ? trim( (string) $term->name ) : '';
		$desc  = $show_desc  ? trim( (string) $term->description ) : '';
		if ( $title === '' && $desc === '' ) return '';
		$cls = 'jp-menu__meta ' . ( $scope === 'global' ? 'jp-menu__meta--global' : 'jp-menu__meta--col' );
		$out  = '<div class="' . esc_attr( $cls ) . '">';
		if ( $title !== '' ) $out .= '<h2 class="jp-menu__meta-title">' . esc_html( $title ) . '</h2>';
		if ( $desc  !== '' ) $out .= '<div class="jp-menu__meta-desc">' . esc_html( $desc ) . '</div>';
		$out .= '</div>';
		return $out;
	}
}

/* --- 1 column --- */
if ( ! function_exists( 'jprm_tpl_render_menu_one_column' ) ) {
	function jprm_tpl_render_menu_one_column( array $ctx ) : void {
		$menu_term           = $ctx['menu_term'] ?? null;
		$show_menu_title     = ! empty( $ctx['show_menu_title'] );
		$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
		$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

		$sections_order      = $ctx['sections_order'] ?? [];
		$sections_data       = $ctx['sections_data'] ?? [];

		$show_section_name   = ! empty( $ctx['show_section_name'] );
		$show_section_desc   = ! empty( $ctx['show_section_desc'] );

		$show_badges         = ! empty( $ctx['show_badges'] );
		$badges_presentation = $ctx['badges_presentation'] ?? 'icon_text';
		$badges_position     = $ctx['badges_position'] ?? 'after_title';

		$label_presentation  = $ctx['label_presentation'] ?? 'icon_text';
		$label_position      = $ctx['label_position'] ?? 'right';
		$label_map           = $ctx['label_map'] ?? null;
		$currency_opts       = $ctx['currency_opts'] ?? [];

		$ib_map              = $ctx['ib_map'] ?? [];

		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
			echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
		}

		echo '<ul class="jp-menu">';
		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
			echo '<li class="jp-menu__meta-li">';
			echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
			echo '</li>';
		}

		foreach ( $sections_order as $tid ) {
			$blk  = $sections_data[ $tid ];
			$term = $blk['term'];

			// Above Info Blocks
			if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
				echo '</li>';
			}

			if ( $term && $show_section_name ) {
				echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
				if ( $show_section_desc && ! empty( $term->description ) ) {
					echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
				}
				echo '</li>';
			}

			foreach ( $blk['items'] as $post ) {
				$post_id = (int) $post->ID;
				$title   = get_the_title( $post_id );
				$desc    = get_post_meta( $post_id, 'jprm_desc', true );

				echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
				echo '  <div class="jp-menu__content">';

				echo '    <div class="jp-menu__titleline">';
				if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
				if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				echo '    </div>';

				if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
				echo '  </div>';

				if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
					echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
				} else {
					echo '<div class="jp-menu__pricegroup"></div>';
				}
				echo '</div></li>';
			}

			// Below Info Blocks
			if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
				echo '</li>';
			}
		}
		echo '</ul>';
	}
}

/* --- 2 columns --- */
if ( ! function_exists( 'jprm_tpl_render_menu_two_columns' ) ) {
	function jprm_tpl_render_menu_two_columns( array $ctx ) : void {
		$menu_term           = $ctx['menu_term'] ?? null;
		$show_menu_title     = ! empty( $ctx['show_menu_title'] );
		$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
		$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

		$sections_order      = $ctx['sections_order'] ?? [];
		$sections_data       = $ctx['sections_data'] ?? [];

		$show_section_name   = ! empty( $ctx['show_section_name'] );
		$show_section_desc   = ! empty( $ctx['show_section_desc'] );

		$show_badges         = ! empty( $ctx['show_badges'] );
		$badges_presentation = $ctx['badges_presentation'] ?? 'icon_text';
		$badges_position     = $ctx['badges_position'] ?? 'after_title';

		$label_presentation  = $ctx['label_presentation'] ?? 'icon_text';
		$label_position      = $ctx['label_position'] ?? 'right';
		$label_map           = $ctx['label_map'] ?? null;
		$currency_opts       = $ctx['currency_opts'] ?? [];

		$split_mode          = $ctx['split_mode'] ?? 'auto';
		$split_after_1       = $ctx['split_after_1'] ?? '';
		$ib_map              = $ctx['ib_map'] ?? [];

		$split_index = null;
		if ( $split_mode === 'manual' && $split_after_1 !== '' ) {
			$target = (int) $split_after_1;
			foreach ( $sections_order as $idx => $tid ) {
				if ( $tid === $target ) { $split_index = $idx; break; }
			}
		}
		if ( $split_index === null ) {
			$total = 0;
			foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }
			$half = (int) ceil( $total / 2 );
			$acc  = 0;
			foreach ( $sections_order as $idx => $tid ) {
				$acc += count( $sections_data[ $tid ]['items'] );
				if ( $acc >= $half ) { $split_index = $idx; break; }
			}
			if ( $split_index === null ) $split_index = count( $sections_order ) - 1;
		}

		$left_sections  = array_slice( $sections_order, 0, $split_index + 1 );
		$right_sections = array_slice( $sections_order, $split_index + 1 );

		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
			echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
		}

		echo '<div class="jp-menu-grid jp-cols-2 jp-menu--cols-2 jp-two-cols">';

		// LEFT
		echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--left">';
		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
			echo '<li class="jp-menu__meta-li">';
			echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
			echo '</li>';
		}
		foreach ( $left_sections as $tid ) {
			$blk  = $sections_data[ $tid ];
			$term = $blk['term'];

			if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
				echo '</li>';
			}

			if ( $term && $show_section_name ) {
				echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
				if ( $show_section_desc && ! empty( $term->description ) ) {
					echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
				}
				echo '</li>';
			}
			foreach ( $blk['items'] as $post ) {
				$post_id = (int) $post->ID;
				$title   = get_the_title( $post_id );
				$desc    = get_post_meta( $post_id, 'jprm_desc', true );
				echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
				echo '  <div class="jp-menu__content">';

				echo '    <div class="jp-menu__titleline">';
				if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
				if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				echo '    </div>';

				if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
				echo '  </div>';
				if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
					echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
				} else {
					echo '<div class="jp-menu__pricegroup"></div>';
				}
				echo '</div></li>';
			}
			if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
				echo '</li>';
			}
		}
		echo '</ul></div>';

		// RIGHT
		echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--right">';
		foreach ( $right_sections as $tid ) {
			$blk  = $sections_data[ $tid ];
			$term = $blk['term'];

			if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
				echo '</li>';
			}

			if ( $term && $show_section_name ) {
				echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
				if ( $show_section_desc && ! empty( $term->description ) ) {
					echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
				}
				echo '</li>';
			}
			foreach ( $blk['items'] as $post ) {
				$post_id = (int) $post->ID;
				$title   = get_the_title( $post_id );
				$desc    = get_post_meta( $post_id, 'jprm_desc', true );
				echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
				echo '  <div class="jp-menu__content">';

				echo '    <div class="jp-menu__titleline">';
				if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
				if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
					echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
				}
				echo '    </div>';

				if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
				echo '  </div>';
				if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
					echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
				} else {
					echo '<div class="jp-menu__pricegroup"></div>';
				}
				echo '</div></li>';
			}
			if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
				echo '<li class="jp-menu__infoblock-li">';
				echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
				echo '</li>';
			}
		}
		echo '</ul></div>';

		echo '</div>';
	}
}

/* --- 3 columns --- */
if ( ! function_exists( 'jprm_tpl_render_menu_three_columns' ) ) {
	function jprm_tpl_render_menu_three_columns( array $ctx ) : void {
		$menu_term           = $ctx['menu_term'] ?? null;
		$show_menu_title     = ! empty( $ctx['show_menu_title'] );
		$show_menu_desc      = ! empty( $ctx['show_menu_desc'] );
		$menu_pos            = $ctx['menu_pos'] ?? 'above_menu';

		$sections_order      = $ctx['sections_order'] ?? [];
		$sections_data       = $ctx['sections_data'] ?? [];

		$show_section_name   = ! empty( $ctx['show_section_name'] );
		$show_section_desc   = ! empty( $ctx['show_section_desc'] );

		$show_badges         = ! empty( $ctx['show_badges'] );
		$badges_presentation = $ctx['badges_presentation'] ?? 'icon_text';
		$badges_position     = $ctx['badges_position'] ?? 'after_title';

		$label_presentation  = $ctx['label_presentation'] ?? 'icon_text';
		$label_position      = $ctx['label_position'] ?? 'right';
		$label_map           = $ctx['label_map'] ?? null;
		$currency_opts       = $ctx['currency_opts'] ?? [];

		$split_mode          = $ctx['split_mode'] ?? 'auto';
		$split_after_1       = $ctx['split_after_1'] ?? '';
		$split_after_2       = $ctx['split_after_2'] ?? '';

		$ib_map              = $ctx['ib_map'] ?? [];

		$total = 0;
		foreach ( $sections_order as $tid ) { $total += count( $sections_data[ $tid ]['items'] ); }

		$col1 = $col2 = $col3 = [];
		if ( $split_mode === 'manual' && $split_after_1 !== '' && $split_after_2 !== '' ) {
			$idx1 = $idx2 = null;
			$t1   = (int) $split_after_1;
			$t2   = (int) $split_after_2;
			foreach ( $sections_order as $i => $tid ) {
				if ( $idx1 === null && $tid === $t1 ) $idx1 = $i;
				if ( $idx2 === null && $tid === $t2 ) $idx2 = $i;
				if ( $idx1 !== null && $idx2 !== null ) break;
			}
			if ( $idx1 !== null && $idx2 !== null && $idx2 > $idx1 ) {
				$col1 = array_slice( $sections_order, 0, $idx1 + 1 );
				$col2 = array_slice( $sections_order, $idx1 + 1, $idx2 - $idx1 );
				$col3 = array_slice( $sections_order, $idx2 + 1 );
			}
		}
		if ( empty( $col1 ) && empty( $col2 ) && empty( $col3 ) ) {
			$t1 = (int) ceil( $total / 3 );
			$t2 = (int) ceil( (2 * $total) / 3 );
			$i1 = null; $i2 = null; $acc = 0;
			foreach ( $sections_order as $idx => $tid ) {
				$acc += count( $sections_data[ $tid ]['items'] );
				if ( $i1 === null && $acc >= $t1 ) $i1 = $idx;
				if ( $i2 === null && $acc >= $t2 ) { $i2 = $idx; break; }
			}
			if ( $i1 === null ) $i1 = 0;
			if ( $i2 === null ) $i2 = max( $i1, count( $sections_order ) - 1 );

			$col1 = array_slice( $sections_order, 0, $i1 + 1 );
			$col2 = array_slice( $sections_order, $i1 + 1, $i2 - $i1 );
			$col3 = array_slice( $sections_order, $i2 + 1 );
		}

		if ( $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'above_menu' ) {
			echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'global' ); // phpcs:ignore
		}

		echo '<div class="jp-menu-grid jp-cols-3 jp-menu--cols-3 jp-three-cols">';

		$cols = [ $col1, $col2, $col3 ];
		$pos  = [ 'left', 'middle', 'right' ];

		foreach ( $cols as $i => $section_ids_chunk ) {
			echo '<div class="jp-col"><ul class="jp-menu jp-menu--col jp-menu--' . esc_attr( $pos[$i] ) . '">';
			if ( $i === 0 && $menu_term && ( $show_menu_title || $show_menu_desc ) && $menu_pos === 'first_column' ) {
				echo '<li class="jp-menu__meta-li">';
				echo jprm_render_menu_meta( $menu_term, $show_menu_title, $show_menu_desc, 'col' ); // phpcs:ignore
				echo '</li>';
			}
			foreach ( $section_ids_chunk as $tid ) {
				$blk  = $sections_data[ $tid ];
				$term = $blk['term'];

				if ( isset( $ib_map[$tid]['above'] ) && ! empty( $ib_map[$tid]['above'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['above'], 'above' ); // phpcs:ignore
					echo '</li>';
				}

				if ( $term && $show_section_name ) {
					echo '<li class="jp-menu__section-header"><h3 class="jp-section__title">' . esc_html( $term->name ) . '</h3>';
					if ( $show_section_desc && ! empty( $term->description ) ) {
						echo '<div class="jp-section__desc">' . esc_html( $term->description ) . '</div>';
					}
					echo '</li>';
				}
				foreach ( $blk['items'] as $post ) {
					$post_id = (int) $post->ID;
					$title   = get_the_title( $post_id );
					$desc    = get_post_meta( $post_id, 'jprm_desc', true );
					echo '<li class="jp-menu__item"><div class="jp-menu__inner">';
					echo '  <div class="jp-menu__content">';

					echo '    <div class="jp-menu__titleline">';
					if ( $show_badges && $badges_position === 'before_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					if ( $title !== '' ) echo '      <h4 class="jp-menu__title">' . esc_html( $title ) . '</h4>';
					if ( $show_badges && $badges_position === 'after_title' && function_exists( 'jprm_render_badges_inline_html' ) ) {
						echo jprm_render_badges_inline_html( $post_id, $badges_presentation ); // phpcs:ignore
					}
					echo '    </div>';

					if ( is_string( $desc ) && $desc !== '' ) echo '    <div class="jp-menu__desc">' . esc_html( $desc ) . '</div>';
					echo '  </div>';
					if ( function_exists( 'jprm_render_pricegroup_html' ) ) {
						echo jprm_render_pricegroup_html( $post_id, $label_presentation, $label_position, $label_map, $currency_opts ); // phpcs:ignore
					} else {
						echo '<div class="jp-menu__pricegroup"></div>';
					}
					echo '</div></li>';
				}

				if ( isset( $ib_map[$tid]['below'] ) && ! empty( $ib_map[$tid]['below'] ) ) {
					echo '<li class="jp-menu__infoblock-li">';
					echo jprm_infoblocks_render_group( $ib_map[$tid]['below'], 'below' ); // phpcs:ignore
					echo '</li>';
				}
			}
			echo '</ul></div>';
		}

		echo '</div>';
	}
}

/* ---- Dispatcher ---- */
$columns = (string) ( $ctx['columns'] ?? '1' );
if ( $columns === '1' ) { jprm_tpl_render_menu_one_column( $ctx ); }
elseif ( $columns === '2' ) { jprm_tpl_render_menu_two_columns( $ctx ); }
else { jprm_tpl_render_menu_three_columns( $ctx ); }
