<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap jprm-menu-builder-wrap">
    <h1><?php esc_html_e('Menu Builder', 'jprm'); ?></h1>

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
                <form method="post"
      action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"
      class="jprm-resequence-form"
      style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php wp_nonce_field( 'jprm_resequence_items', '_jprm_resort_nonce' ); ?>
    <input type="hidden" name="action" value="jprm_resequence_items">
    <input type="hidden" name="menu_id" id="jprm-resequence-menu-id" value="">
    <strong><?php esc_html_e('Resequence all items in all sections:', 'jprm'); ?></strong>
    <button class="button" name="direction" value="ASC"  type="submit"><?php esc_html_e('ASC',  'jprm'); ?></button>
    <button class="button" name="direction" value="DESC" type="submit"><?php esc_html_e('DESC', 'jprm'); ?></button>
    <span class="description" style="opacity:.75;">
        <?php esc_html_e('One-time operation — rewrites menu_order.', 'jprm'); ?>
    </span>
</form>

            </div>
        </div>

        <!-- RIGHT: Add Section + Existing Items -->
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
</div>
<script>
(function(){
  const sel = document.getElementById('jprm-menu-select');
  const hid = document.getElementById('jprm-resequence-menu-id');
  function sync(){ if (sel && hid) hid.value = sel.value || ''; }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sync, {once:true});
  } else {
    sync();
  }
  if (sel) sel.addEventListener('change', sync);
})();
</script>