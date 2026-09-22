/* Explicit Matrix columns: browsers need not fragment tables or repeat headers. */
(function () {
    'use strict';
    const states = new WeakMap();
    const widths = new WeakMap();
    let pending = false;
    let hooked = false;
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.target.isConnected) { observer.unobserve(entry.target); return; }
            const width = entry.contentRect.width;
            if (widths.get(entry.target) !== width) {
                widths.set(entry.target, width);
                schedule();
            }
        });
    });

    function prepare(matrix) {
        const host = matrix.parentElement;
        const wrapper = document.createElement('div');
        wrapper.className = 'jp-matrix-columns';
        const header = matrix.querySelector('.jp-matrix__row--header');
        const groups = [];
        let preceding = [];
        Array.from(matrix.children).forEach(function (child) {
            if (child === header) { return; }
            preceding.push(child);
            if (child.hasAttribute('data-post-id')) {
                groups.push(preceding);
                preceding = [];
            }
        });
        if (!groups.length) { return; }
        if (preceding.length) { groups[groups.length - 1].push.apply(groups[groups.length - 1], preceding); }
        const template = matrix.cloneNode(false);
        matrix.replaceWith(wrapper);
        wrapper.appendChild(matrix);
        host.classList.add('jp-menu__section-items--matrix');
        states.set(wrapper, { header: header, groups: groups, template: template });
        if (observer) { observer.observe(host); }
    }

    function balance(wrapper) {
        if (!wrapper.getBoundingClientRect().width || !wrapper.getClientRects().length) { return; }
        const state = states.get(wrapper);
        if (!state) { return; }
        const count = Math.min(state.groups.length, Math.max(1, parseInt(getComputedStyle(wrapper).columnCount, 10) || 1));
        wrapper.style.setProperty('--jprm-matrix-group-count', count);
        const columns = [];
        for (let i = 0; i < count; i++) {
            const column = state.template.cloneNode(false);
            if (state.header) { column.appendChild(state.header.cloneNode(true)); }
            columns.push(column);
        }
        // Measure at the final column width, keeping actual item nodes and order.
        state.groups.forEach(function (group) { group.forEach(function (node) { columns[0].appendChild(node); }); });
        wrapper.replaceChildren.apply(wrapper, columns);
        const heights = state.groups.map(function (group) {
            return Math.max(1, group.reduce(function (height, node) { return height + node.getBoundingClientRect().height; }, 0));
        });
        let remaining = heights.reduce(function (sum, height) { return sum + height; }, 0);
        let index = 0;
        columns.forEach(function (column, columnIndex) {
            const columnsLeft = count - columnIndex;
            const target = remaining / columnsLeft;
            let used = 0;
            let items = 0;
            while (index < state.groups.length) {
                if (items && columnsLeft > 1 && (state.groups.length - index <= columnsLeft - 1 || Math.abs(used - target) <= Math.abs(used + heights[index] - target))) { break; }
                state.groups[index].forEach(function (node) { column.appendChild(node); });
                used += heights[index++];
                items++;
            }
            remaining -= used;
        });
    }

    function schedule() {
        if (pending) { return; }
        pending = true;
        requestAnimationFrame(function () {
            pending = false;
            document.querySelectorAll('.jp-menu-grid--section-columns .jp-menu__section-items > .jp-matrix').forEach(prepare);
            document.querySelectorAll('.jp-matrix-columns').forEach(balance);
        });
    }
    function hookEditor() {
        if (!hooked && window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/jprm_restaurant_menu.default', schedule);
            hooked = true;
        }
        schedule();
    }
    if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', hookEditor); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', hookEditor); } else { hookEditor(); }
    window.addEventListener('resize', schedule);
    window.addEventListener('load', hookEditor);
    document.addEventListener('load', function (event) {
        // Cloned header images must not trigger an endless rebuild/load loop.
        if (event.target instanceof Element && event.target.closest('.jp-matrix__row[data-post-id]') && event.target.closest('.jp-matrix-columns')) { schedule(); }
    }, true);
    if (document.fonts) { document.fonts.ready.then(schedule); }
}());
