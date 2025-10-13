<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap jprm-menu-builder-wrap">
    <h1><?php esc_html_e('Menu Builder', 'jprm'); ?></h1>

    <div class="jprm-toolbar">
        <label for="jprm-menu-select"><?php esc_html_e('Select Menu:', 'jprm'); ?></label>
        <select id="jprm-menu-select"></select>
        <button class="button button-secondary" id="jprm-refresh"><?php esc_html_e('Refresh', 'jprm'); ?></button>
        <span class="spinner is-active" id="jprm-loading" style="display:none;"></span>
    </div>

    <div class="jprm-columns">
        <!-- LEFT: Sections -->
        <div class="jprm-left">
            <div class="card">
                <h2><?php esc_html_e('Sections', 'jprm'); ?></h2>

                <ul id="jprm-tree" class="jprm-flat"></ul>

                <div class="jprm-actions">
                    <button class="button button-primary" id="jprm-save"><?php esc_html_e('Save Structure', 'jprm'); ?></button>
                    <button class="button" id="jprm-expand"><?php esc_html_e('Expand all', 'jprm'); ?></button>
                    <button class="button" id="jprm-collapse"><?php esc_html_e('Collapse all', 'jprm'); ?></button>
                </div>
            </div>
        </div>

        <!-- RIGHT: Add Section + Add Item -->
        <div class="jprm-right">
            <div class="card">
                <h2><?php esc_html_e('Add Section', 'jprm'); ?></h2>
                <div class="jprm-form">
                    <input type="text" id="jprm-new-section-title" class="regular-text" placeholder="<?php esc_attr_e('Section title…', 'jprm'); ?>">
                    <button class="button button-primary" id="jprm-add-section" style="margin-top:8px;"><?php esc_html_e('Add', 'jprm'); ?></button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('Tip: drag slightly to the right to create a sub-section; drag left to outdent.', 'jprm'); ?>
                </p>
            </div>

            <div class="card" style="margin-top:16px;">
                <h2><?php esc_html_e('Add Item', 'jprm'); ?></h2>
                <div class="jprm-form">
                    <label for="jprm-item-target-section" style="display:block;margin-bottom:6px;"><?php esc_html_e('Target Section', 'jprm'); ?></label>
                    <select id="jprm-item-target-section" style="width:100%;max-width:100%;"></select>

                    <button class="button" id="jprm-open-add-item" style="margin-top:8px;">
                        <?php esc_html_e('Create Item in Editor (new tab)', 'jprm'); ?>
                    </button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e('This opens the standard editor. In Step 2 we’ll add quick-add and drag items between sections directly here.', 'jprm'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
