<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap jprm-menu-builder-wrap">
    <h1><?php esc_html_e('Menu Builder', 'jprm'); ?></h1>

    <div class="jprm-menu-builder-notice jprm-menu-builder-notice--hidden jprm-menu-builder-notice--top"></div>

    <div class="jprm-toolbar">
        <label for="jprm-menu-select"><?php esc_html_e('Select Menu:', 'jprm'); ?></label>
        <select id="jprm-menu-select"></select>
        <button class="button button-secondary" id="jprm-refresh"><?php esc_html_e('Refresh', 'jprm'); ?></button>

        <!-- Toggle (top) -->
        <button class="button jprm-toggle-all" data-collapsed="0">
            <?php esc_html_e('Collapse all', 'jprm'); ?>
        </button>

        <span class="spinner is-active" id="jprm-loading" style="display:none;"></span>
    </div>

    <div class="jprm-columns">
        <!-- LEFT: Sections + items -->
        <div class="jprm-left">
            <div class="card">
                <h2><?php esc_html_e('Sections', 'jprm'); ?></h2>

                <ul id="jprm-tree" class="jprm-flat"></ul>

                <div class="jprm-actions">
                    <button class="button button-primary" id="jprm-save"><?php esc_html_e('Save Structure', 'jprm'); ?></button>
                    <!-- Toggle (bottom) -->
                    <button class="button jprm-toggle-all" data-collapsed="0">
                        <?php esc_html_e('Collapse all', 'jprm'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT: Add Section + Existing Items -->
        <div class="jprm-right">
            <div class="card">
                <h2><?php esc_html_e('Add Section', 'jprm'); ?></h2>
                <div class="jprm-form">
                    <h3><?php esc_html_e('Choose Existing Section', 'jellopoint-restaurant-menu'); ?></h3>
                    <select id="jprm-existing-section" style="width:100%;max-width:100%;"></select>
                    <button class="button" id="jprm-attach-section" style="margin-top:8px;"><?php esc_html_e('Add Existing Section', 'jellopoint-restaurant-menu'); ?></button>

                    <hr style="margin:16px 0;">
                    <h3><?php esc_html_e('Create New Section', 'jellopoint-restaurant-menu'); ?></h3>
                    <input type="text" id="jprm-new-section-title" class="regular-text" placeholder="<?php esc_attr_e('Section title…', 'jprm'); ?>">
                    <button class="button button-primary" id="jprm-add-section" style="margin-top:8px;"><?php esc_html_e('Create and Add Section', 'jellopoint-restaurant-menu'); ?></button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Tip: drag slightly to the right to create a sub-section; drag left to outdent.', 'jprm'); ?>
                </p>
            </div>

            <div class="card" style="margin-top:16px;">
                <h2><?php esc_html_e('Add Existing Items', 'jprm'); ?></h2>
                <div class="jprm-form">
                    <label style="display:block;margin-bottom:6px;"><?php esc_html_e('Target Section', 'jprm'); ?></label>
                    <select id="jprm-item-target-section" style="width:100%;max-width:100%;"></select>

                    <div style="display:flex;align-items:center;gap:8px;margin:10px 0 6px;">
                        <strong><?php esc_html_e('Unassigned Items', 'jprm'); ?></strong>
                        <label><input type="checkbox" id="jprm-unassigned-all"> <?php esc_html_e('Select all', 'jprm'); ?></label>
                    </div>
                    <div id="jprm-unassigned-list" style="max-height:280px;overflow:auto;border:1px solid #dcdcde;padding:6px;"></div>

                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <button class="button button-primary" id="jprm-assign-item"><?php esc_html_e('Add selected to Section', 'jprm'); ?></button>
                        <a class="button" id="jprm-open-add-item" target="_blank" rel="noopener">
                            <?php esc_html_e('Create Item in Editor (new tab)', 'jprm'); ?>
                        </a>
                    </div>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Tick one or more items and add them to the chosen Section.', 'jprm'); ?>
                </p>
            </div>
        </div>
    </div>
    <div class="jprm-menu-builder-notice jprm-menu-builder-notice--hidden jprm-menu-builder-notice--bottom"></div>
</div>
