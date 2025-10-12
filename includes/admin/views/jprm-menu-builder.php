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
        <div class="jprm-left">
            <div class="card">
                <h2><?php esc_html_e('Add Section', 'jprm'); ?></h2>
                <input type="text" id="jprm-new-section-title" class="regular-text" placeholder="<?php esc_attr_e('Section title…', 'jprm'); ?>">
                <button class="button button-primary" id="jprm-add-section"><?php esc_html_e('Add', 'jprm'); ?></button>
                <p class="description"><?php esc_html_e('Quick-add; advanced settings available in the section editor.', 'jprm'); ?></p>
            </div>
        </div>
        <div class="jprm-main">
            <div class="card">
                <h2><?php esc_html_e('Sections', 'jprm'); ?></h2>
                <ul id="jprm-tree" class="jprm-tree">
                    <!-- JS renders the nestable list here -->
                </ul>
                <div class="jprm-actions">
                    <button class="button button-primary" id="jprm-save"><?php esc_html_e('Save Structure', 'jprm'); ?></button>
                    <button class="button" id="jprm-expand"><?php esc_html_e('Expand all', 'jprm'); ?></button>
                    <button class="button" id="jprm-collapse"><?php esc_html_e('Collapse all', 'jprm'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
