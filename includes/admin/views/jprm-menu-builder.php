<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap jprm-menu-builder-wrap">
    <h1><?php esc_html_e('Menu Builder', 'jellopoint-restaurant-menu'); ?></h1>

    <div class="jprm-menu-builder-notice jprm-menu-builder-notice--hidden jprm-menu-builder-notice--top"></div>

    <div class="jprm-toolbar">
        <label for="jprm-menu-select"><?php esc_html_e('Select Menu:', 'jellopoint-restaurant-menu'); ?></label>
        <select id="jprm-menu-select"></select>
        <button class="button button-secondary" id="jprm-refresh"><?php esc_html_e('Refresh', 'jellopoint-restaurant-menu'); ?></button>

        <!-- Toggle (top) -->
        <button class="button jprm-toggle-all" data-collapsed="0">
            <?php esc_html_e('Collapse all', 'jellopoint-restaurant-menu'); ?>
        </button>

        <span class="spinner is-active" id="jprm-loading" style="display:none;"></span>
    </div>

    <div class="jprm-columns">
        <!-- LEFT: Sections + items -->
        <div class="jprm-left">
            <div class="card">
                <h2><?php esc_html_e('Sections', 'jellopoint-restaurant-menu'); ?></h2>

                <ul id="jprm-tree" class="jprm-flat"></ul>

                <div class="jprm-actions">
                    <button class="button button-primary" id="jprm-save"><?php esc_html_e('Save Structure', 'jellopoint-restaurant-menu'); ?></button>
                    <!-- Toggle (bottom) -->
                    <button class="button jprm-toggle-all" data-collapsed="0">
                        <?php esc_html_e('Collapse all', 'jellopoint-restaurant-menu'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT: Add Section + Existing Items -->
        <div class="jprm-right">
            <div class="card">
                <h2><?php esc_html_e('Add Section', 'jellopoint-restaurant-menu'); ?></h2>
                <div class="jprm-form">
                    <h3><?php esc_html_e('Choose Existing Section', 'jellopoint-restaurant-menu'); ?></h3>
                    <select id="jprm-existing-section" style="width:100%;max-width:100%;"></select>
                    <button class="button" id="jprm-attach-section" style="margin-top:8px;"><?php esc_html_e('Add Existing Section', 'jellopoint-restaurant-menu'); ?></button>

                    <hr style="margin:16px 0;">
                    <h3><?php esc_html_e('Create New Section', 'jellopoint-restaurant-menu'); ?></h3>
                    <input type="text" id="jprm-new-section-title" class="regular-text" placeholder="<?php esc_attr_e('Section title…', 'jellopoint-restaurant-menu'); ?>">
                    <button class="button button-primary" id="jprm-add-section" style="margin-top:8px;"><?php esc_html_e('Create and Add Section', 'jellopoint-restaurant-menu'); ?></button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Tip: drag slightly to the right to create a sub-section; drag left to outdent.', 'jellopoint-restaurant-menu'); ?>
                </p>
            </div>

            <div class="card" style="margin-top:16px;">
                <h2><?php esc_html_e('Add Existing Items', 'jellopoint-restaurant-menu'); ?></h2>
                <div class="jprm-form">
                    <label style="display:block;margin-bottom:6px;"><?php esc_html_e('Target Section', 'jellopoint-restaurant-menu'); ?></label>
                    <select id="jprm-item-target-section" style="width:100%;max-width:100%;"></select>

                    <div style="display:flex;align-items:center;gap:8px;margin:10px 0 6px;">
                        <strong><?php esc_html_e('Unassigned Items', 'jellopoint-restaurant-menu'); ?></strong>
                        <label><input type="checkbox" id="jprm-unassigned-all"> <?php esc_html_e('Select all', 'jellopoint-restaurant-menu'); ?></label>
                    </div>
                    <div id="jprm-unassigned-list" style="max-height:280px;overflow:auto;border:1px solid #dcdcde;padding:6px;"></div>

                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <button class="button button-primary" id="jprm-assign-item"><?php esc_html_e('Add selected to Section', 'jellopoint-restaurant-menu'); ?></button>
                        <a class="button" id="jprm-open-add-item" target="_blank" rel="noopener">
                            <?php esc_html_e('Create Item in Editor (new tab)', 'jellopoint-restaurant-menu'); ?>
                        </a>
                    </div>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Tick one or more items and add them to the chosen Section.', 'jellopoint-restaurant-menu'); ?>
                </p>
            </div>

			<div class="card" style="margin-top:16px;">
				<h2><?php esc_html_e( 'Print/PDF Info Blocks', 'jellopoint-restaurant-menu' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose which reusable Info Blocks appear in printed and PDF menus. Website placement is configured separately in the Elementor widget.', 'jellopoint-restaurant-menu' ); ?></p>
				<select id="jprm-info-block" style="width:100%"></select>
				<select id="jprm-info-section" style="width:100%;margin-top:8px"></select>
				<select id="jprm-info-position" style="width:100%;margin-top:8px"><option value="above"><?php esc_html_e( 'Above Section', 'jellopoint-restaurant-menu' ); ?></option><option value="below"><?php esc_html_e( 'Below Section', 'jellopoint-restaurant-menu' ); ?></option></select>
				<p><button class="button button-primary" id="jprm-add-info-block"><?php esc_html_e( 'Add Info Block', 'jellopoint-restaurant-menu' ); ?></button> <a class="button" id="jprm-new-info-block" target="_blank" rel="noopener"><?php esc_html_e( 'Create Info Block', 'jellopoint-restaurant-menu' ); ?></a></p>
				<div id="jprm-info-placements"></div>
			</div>
        </div>
    </div>
    <div class="jprm-menu-builder-notice jprm-menu-builder-notice--hidden jprm-menu-builder-notice--bottom"></div>
</div>
