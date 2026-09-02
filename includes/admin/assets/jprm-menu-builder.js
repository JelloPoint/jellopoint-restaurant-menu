(function($){
  function apiUrl(path){
    const parts = String(path || '').replace(/^\//, '').split('?');
    const root = String(JPRM_MENU_BUILDER.root || '').replace(/\/$/, '');
    const url = root + '/' + parts.shift();
    const query = parts.join('?');

    if (!query) return url;

    // With plain WordPress permalinks, rest_url() already contains
    // ?rest_route=...; additional request arguments must therefore use &.
    return url + (root.indexOf('?') === -1 ? '?' : '&') + query;
  }
  function apiGet(path){ return $.ajax({ url: apiUrl(path), method:'GET', beforeSend:x=>x.setRequestHeader('X-WP-Nonce',JPRM_MENU_BUILDER.nonce) }); }
  function apiPost(path,data){ return $.ajax({ url: apiUrl(path), method:'POST', contentType:'application/json; charset=utf-8', data:JSON.stringify(data||{}), beforeSend:x=>x.setRequestHeader('X-WP-Nonce',JPRM_MENU_BUILDER.nonce) }); }

  const state = { menus:[], sections:[], availableSections:[], items:[], unassigned:[], currentMenu:null };
  const INDENT = 28, MAX_DEPTH = 6;
  let drag = null;

   function toast(msg, type){
    const isError = (type === 'error');
    const $boxes = $('.jprm-menu-builder-notice');

    $boxes.each(function(){
      const $box = $(this);

      // Update text and base style
      $box
        .text(msg)
        .removeClass('jprm-menu-builder-notice--error')
        .addClass('jprm-menu-builder-notice--visible');

      if (isError) {
        $box.addClass('jprm-menu-builder-notice--error');
      }

      // Clear any previous hide-timer
      const prev = $box.data('jprmTimeout');
      if (prev) {
        clearTimeout(prev);
      }

      // Auto-hide after 4s
      const timeout = setTimeout(function(){
        $box.removeClass('jprm-menu-builder-notice--visible');
      }, 4000);

      $box.data('jprmTimeout', timeout);
    });
  }


  function apiFailToMessage(xhr){
    try {
      const j = xhr && xhr.responseJSON ? xhr.responseJSON : null;
      if (j && j.code === 'jprm_cross_menu') return 'That action is blocked: sections/items cannot move across different Menus.';
      if (j && j.message) return j.message;
    } catch(e){}
    return 'Unknown error';
  }

  function setLoading(on){ $('#jprm-loading')[on?'show':'hide'](); }

  function loadMenus(){
    setLoading(true);
    return apiGet('menu-builder/menus').done(res=>{
      state.menus = res.menus||[];
      const $sel = $('#jprm-menu-select').empty();
      if(!state.menus.length){ $sel.append($('<option>').text('— No menus found —').prop('disabled',true)); state.currentMenu=null; return; }
      state.menus.forEach(m=>$sel.append($('<option>').val(m.id).text(m.title)));
      if(!state.currentMenu) state.currentMenu = state.menus[0].id;
      $sel.val(state.currentMenu);
    }).always(()=>setLoading(false));
  }
  function loadSections(){ if(!state.currentMenu){ $('#jprm-tree').empty(); return $.Deferred().resolve().promise(); } setLoading(true); return apiGet('menu-builder/sections?menu_id='+state.currentMenu).done(res=>{ state.sections = res.sections||[]; }).always(()=>setLoading(false)); }
  function loadAvailableSections(){ if(!state.currentMenu){ state.availableSections=[]; return $.Deferred().resolve().promise(); } return apiGet('menu-builder/sections/available?menu_id='+state.currentMenu).done(res=>{ state.availableSections=res.sections||[]; fillExistingSectionSelect(); }); }
  function loadItems(){ if(!state.currentMenu){ state.items=[]; return $.Deferred().resolve().promise(); } return apiGet('menu-builder/items?menu_id='+state.currentMenu).done(res=>{ state.items = res.items||[]; }); }
  function loadUnassigned(){ if(!state.currentMenu){ state.unassigned=[]; return $.Deferred().resolve().promise(); } return apiGet('menu-builder/items?menu_id='+state.currentMenu+'&unassigned=1').done(res=>{ state.unassigned = res.items||[]; }); }

  function applyIndent($li, depth){ $li.attr('data-depth',depth).css('margin-left',(depth*INDENT)+'px'); }
  function clampDepth(depth,$ph){ depth=Math.max(0,Math.min(MAX_DEPTH,depth)); const $prev=$ph.prev('.jprm-item'); if($prev.length){ const pd=parseInt($prev.attr('data-depth'),10)||0; depth=Math.min(depth,pd+1); } else depth=0; return depth; }

  function buildSectionTree(){
    const byId={}; state.sections.forEach(s=>byId[s.id]={...s,depth:0,children:[]});
    const roots=[]; state.sections.forEach(s=>{ if(s.parent_id&&byId[s.parent_id]) byId[s.parent_id].children.push(byId[s.id]); else roots.push(byId[s.id]); });
    (function setD(ns,d){ ns.forEach(n=>{ n.depth=d; if(n.children) setD(n.children,d+1); }); })(roots,0);
    const flat=[]; (function walk(ns){ ns.forEach(n=>{ flat.push({id:n.id,title:n.title,depth:n.depth}); walk(n.children||[]); }); })(roots);
    return {roots,flat};
  }

  function fillTargetSectionSelect(){
    const $sel=$('#jprm-item-target-section').empty();
    const tree=buildSectionTree();
    if(!tree.flat.length){ $sel.append($('<option>').text('— No sections —').prop('disabled',true)); return; }
    tree.flat.forEach(s=>{ const indent=new Array(s.depth+1).join('— '); $sel.append($('<option>').val(s.id).text(indent+s.title)); });
    $sel.val(tree.flat[0].id);
  }
  function fillExistingSectionSelect(){
    const $sel=$('#jprm-existing-section').empty();
    if(!state.availableSections.length){ $sel.append($('<option>').text('— No other sections available —').prop('disabled',true)); $('#jprm-attach-section').prop('disabled',true); return; }
    state.availableSections.forEach(s=>$sel.append($('<option>').val(s.id).text(s.title)));
    $('#jprm-attach-section').prop('disabled',false);
  }
  function renderUnassignedCheckboxes(){
    const $box=$('#jprm-unassigned-list').empty();
    if(!state.unassigned.length){ $box.append($('<div>').addClass('jprm-muted').text('— No unassigned items —')); return; }
    state.unassigned.forEach(it=>{ const id='ua-'+it.id; const label=it.price?`${it.title} • ${it.price}`:it.title;
      const $row=$('<label for="'+id+'">').addClass('jprm-ua-row');
      $row.append($('<input type="checkbox">').attr('id',id).attr('data-id',it.id));
      $row.append($('<span>').text(label));
      $box.append($row);
    });
    $('#jprm-unassigned-all').prop('checked',false);
  }

  function renderList(){
    const itemsBySection={};
    state.items.forEach(it=>{ if(!itemsBySection[it.section_id]) itemsBySection[it.section_id]=[]; itemsBySection[it.section_id].push(it); });
    Object.values(itemsBySection).forEach(a=>a.sort((x,y)=>(x.order_in_section||0)-(y.order_in_section||0)||x.title.localeCompare(y.title)));

    const byId={}; state.sections.forEach(s=>byId[s.id]={...s,depth:0,children:[]});
    const roots=[]; state.sections.forEach(s=>{ if(s.parent_id&&byId[s.parent_id]) byId[s.parent_id].children.push(byId[s.id]); else roots.push(byId[s.id]); });
    (function setD(ns,d){ ns.forEach(n=>{ n.depth=d; if(n.children) setD(n.children,d+1); }); })(roots,0);

    const $ul=$('#jprm-tree').empty().removeClass().addClass('jprm-flat');

    function actionIcon(cls,title){ return $('<span class="dashicons '+cls+' jprm-act" title="'+title+'">'); }

    function addSectionRow(sec){
      const $li=$('<li>').attr('data-id',sec.id).attr('data-depth',sec.depth).addClass('jprm-item jprm-section');
      const $toggle=$('<button type="button" class="jprm-toggle button button-small" title="Toggle">▾</button>');
      const $right=$('<span class="jprm-actions">').append(actionIcon('dashicons-dismiss','Unassign section').attr('data-action','section-unassign'));
      const $row=$('<div class="jprm-row">')
        .append($('<span class="jprm-handle dashicons dashicons-menu" title="Drag section"></span>'))
        .append($toggle)
        .append($('<span class="jprm-title">').text(sec.title))
        .append($right);
      $li.append($row); $ul.append($li); applyIndent($li,sec.depth);

      const its=itemsBySection[sec.id]||[];
      if(!its.length){
        const depth=sec.depth+1;
        const $hint=$('<li>').addClass('jprm-item jprm-entry').attr('data-depth',depth);
        $hint.append($('<div class="jprm-row">').append($('<span class="jprm-title jprm-muted">').text('— No items in this section —')));
        $ul.append($hint); applyIndent($hint,depth);
      } else {
        its.forEach(it=>{
          const depth=sec.depth+1;
          const $liIt=$('<li>').attr('data-id',it.id).attr('data-depth',depth).attr('data-section-id',sec.id).addClass('jprm-item jprm-entry is-item');
          const $right=$('<span class="jprm-actions">').append(actionIcon('dashicons-dismiss','Unassign item').attr('data-action','item-unassign'));
          const $rowIt=$('<div class="jprm-row">')
            .append($('<span class="jprm-handle dashicons dashicons-menu" title="Drag item"></span>'))
            .append($('<span class="jprm-title">').text(it.price?`${it.title} • ${it.price}`:it.title))
            .append($right);
          $liIt.append($rowIt); $ul.append($liIt); applyIndent($liIt,depth);
        });
      }
      (sec.children||[]).forEach(addSectionRow);
    }

    roots.forEach(addSectionRow);
    fillTargetSectionSelect();
    renderUnassignedCheckboxes();
    initSortable($ul);
    setToggleAllLabel(false);
  }
  // === PAYLOAD HELPERS (used by drag-stop and the Save button) ===
  function buildTreeFromDOM(){
    const stack = [], out = [];
    $('#jprm-tree > li.jprm-section').each(function(idx){
      const $li = $(this);
      const id = parseInt($li.attr('data-id'),10);
      const depth = parseInt($li.attr('data-depth'),10)||0;
      stack.length = depth;
      const parentId = depth>0 ? (stack[depth-1]||0) : 0;
      out.push({ id, parent_id: parentId, order: idx });
      stack[depth] = id;
    });
    return out;
  }

  function buildItemsPayloadFromDOM(){
    const arr = [];
    let currentSectionId = 0, order = -1;
    $('#jprm-tree > li.jprm-item').each(function(){
      const $li = $(this);
      if ($li.hasClass('jprm-section')){
        currentSectionId = parseInt($li.attr('data-id'),10);
        order = -1;
      } else if ($li.hasClass('is-item')){
        const depth = parseInt($li.attr('data-depth'),10)||1;
        if (depth < 1 || !currentSectionId) return;
        order++;
        const id = parseInt($li.attr('data-id'),10);
        arr.push({ id, section_id: currentSectionId, order });
      }
    });
    return arr;
  }
  function persistSectionsOnly(){
    if(!state.currentMenu) return;
    const tree = buildTreeFromDOM();
    // silent save (no spinner during drag)
    apiPost('menu-builder/sections/order', { tree, menu_id: state.currentMenu })
      .then(()=> loadSections().then(()=>{ // normalise depths/parents
        renderList();
        // don't auto-expand; keep current view stable
        setToggleAllLabel(false);
      }))
      .fail(x => toast(apiFailToMessage(x)));
  }

  function persistItemsOnly(){
    if(!state.currentMenu) return;
    const items = buildItemsPayloadFromDOM();
    apiPost('menu-builder/items/order', { menu_id: state.currentMenu, items })
      .fail(x => toast(apiFailToMessage(x)));
  }

  function initSortable($ul){
    try{ $ul.sortable('destroy'); }catch(e){}

    function buildSectionHelper($item){
      const startDepth=parseInt($item.attr('data-depth'),10)||0;
      const $helper=$('<div class="jprm-helper-group">');
      $helper.append($item.clone());

      // capture and hide the entire block following the section (descendants)
      const block=[]; let $next=$item.next();
      while($next.length){
        const d=parseInt($next.attr('data-depth'),10)||0;
        if(d>startDepth){
          block.push($next[0]);
          $helper.append($next.clone());
          $next.data('jprm-old-display',$next.css('display'));
          $next.css('display','none').addClass('jprm-drag-hidden');
          $next=$next.next();
        } else break;
      }
      $item.data('jprm-drag-block',block).data('jprm-start-depth',startDepth);
      $helper.find('> li').each(function(){
        const d=parseInt($(this).attr('data-depth'),10)||0;
        $(this).css('margin-left',(d*INDENT)+'px');
      });
      return $helper;
    }

    function enforceItemDepth(ui){
      if(!drag||drag.isSection) return;
      let $prev=ui.placeholder.prev(), sectionDepth=0;
      while($prev.length){
        if($prev.hasClass('jprm-section')){
          sectionDepth=parseInt($prev.attr('data-depth'),10)||0;
          break;
        }
        $prev=$prev.prev();
      }
      applyIndent(ui.placeholder, sectionDepth+1);
    }

    // the default selector (sections + items)
    const DEFAULT_ITEMS = '> li.jprm-section, > li.is-item';

    $ul.sortable({
      placeholder:'jprm-placeholder',
      items: DEFAULT_ITEMS,
      handle: '.jprm-handle',
      tolerance:'pointer',
      forcePlaceholderSize:true,
      helper:function(e,item){ return $(item).hasClass('jprm-section') ? buildSectionHelper($(item)) : item.clone(); },

      start:function(e,ui){
        $('body').addClass('jprm-sorting');
        const isSection=ui.item.hasClass('jprm-section');
        const startDepth=parseInt(ui.item.attr('data-depth'),10)||0;
        drag={ startX:e.pageX, startDepth, isSection, $item:ui.item };
        ui.placeholder.height(ui.item.outerHeight());
        applyIndent(ui.placeholder,startDepth);

        if(isSection){
          // **Critical**: while dragging a section, only sections are sortable targets
          $ul.sortable('option','items','> li.jprm-section');
        } else {
          enforceItemDepth(ui);
        }
      },

      sort:function(e,ui){
        if(!drag) return;
        if(drag.isSection){
          const deltaX=e.pageX-drag.startX;
          let newDepth=drag.startDepth+Math.round(deltaX/INDENT);
          newDepth=clampDepth(newDepth,ui.placeholder);
          applyIndent(ui.placeholder,newDepth);
          // no need to snap; items aren't valid targets during section drag
        } else {
          enforceItemDepth(ui);
        }
      },

      beforeStop:function(e,ui){
        applyIndent(ui.item, parseInt(ui.placeholder.attr('data-depth'),10)||0 );
      },

      stop:function(e,ui){
        $('body').removeClass('jprm-sorting');

        if(drag && drag.isSection){
          // restore default targets
          $ul.sortable('option','items', DEFAULT_ITEMS);

          // re-attach the hidden descendants block immediately after the section
          const block = ui.item.data('jprm-drag-block') || [];
          if(block.length){
            for(let i=0;i<block.length;i++){
              const $n=$(block[i]);
              $n.css('display',$n.data('jprm-old-display')||'');
              $n.removeClass('jprm-drag-hidden');
              $n.insertAfter(ui.item);
            }
          }
          ui.item.removeData('jprm-drag-block jprm-start-depth');

          // save ONLY sections (parents + order)
          persistSectionsOnly();
        } else {
          // save ONLY items (section assignment + order)
          persistItemsOnly();
        }
        drag=null;
      }

    });
  }

  /* ---------- Collapse / Expand ---------- */
  function setToggleAllLabel(collapsed){
    $('.jprm-toggle-all').text(collapsed ? 'Expand all' : 'Collapse all')
                         .attr('data-collapsed', collapsed ? '1' : '0');
  }
  function collapseAll(){
    $('#jprm-tree > li.jprm-item').each(function(){
      const d=parseInt($(this).attr('data-depth'),10)||0;
      if(d>0) $(this).hide(); else $(this).show();
    });
    $('.jprm-toggle').text('▸');
    setToggleAllLabel(true);
  }
  function expandAll(){
    $('#jprm-tree > li.jprm-item').show();
    $('.jprm-toggle').text('▾');
    setToggleAllLabel(false);
  }
  $(document).on('click','.jprm-toggle-all',function(){
    const collapsed=$(this).attr('data-collapsed')==='1';
    if(collapsed) expandAll(); else collapseAll();
  });

  /* ---------- Actions ---------- */
  $(document).on('click','.jprm-section .jprm-act',function(e){
    e.preventDefault();
    const $sec=$(this).closest('li.jprm-section'); const sectionId=parseInt($sec.attr('data-id'),10);
    if(!state.currentMenu||!sectionId) return;
    if($(this).data('action')==='section-unassign'){
      if(!confirm('Unassign this section from the menu?')) return;
      setLoading(true);
      apiPost('menu-builder/section/unassign',{menu_id:state.currentMenu,section_id:sectionId})
        .done(()=> chainLoadAndRender(true))
        .fail(x=>toast(apiFailToMessage(x)))
        .always(()=>setLoading(false));
    }
  });

  $(document).on('click','.is-item .jprm-act',function(e){
    e.preventDefault();
    const id=parseInt($(this).closest('li.is-item').attr('data-id'),10); if(!id) return;
    if($(this).data('action')==='item-unassign'){
      if(!confirm('Remove this item from its section?')) return;
      setLoading(true);
      apiPost('menu-builder/item/unassign',{id})
        .done(()=> chainLoadAndRender(false))
        .fail(x=>toast(apiFailToMessage(x)))
        .always(()=>setLoading(false));
    }
  });

  function chainLoadAndRender(expand){
    return loadSections().then(()=>loadAvailableSections()).then(()=>loadItems()).then(()=>loadUnassigned()).then(()=>{ renderList(); if(expand) expandAll(); else setToggleAllLabel(false); });
  }

  $(document).on('change','#jprm-menu-select',function(){ state.currentMenu=parseInt($(this).val(),10)||null; chainLoadAndRender(true); });
  $('#jprm-refresh').on('click',function(){ loadMenus().then(()=>chainLoadAndRender(true)); });

  $('#jprm-add-section').on('click',function(){
    const title=$('#jprm-new-section-title').val().trim();
    if(!title) return toast('Please enter a section title.');
    if(!state.currentMenu) return toast('Select a Menu first.');
    setLoading(true);
    apiPost('menu-builder/section',{name:title,parent:0,menu_id:state.currentMenu})
      .done(()=>{ $('#jprm-new-section-title').val(''); chainLoadAndRender(true); })
      .fail(x=>toast(apiFailToMessage(x)))
      .always(()=>setLoading(false));
  });

  $('#jprm-attach-section').on('click',function(){
    const sectionId=parseInt($('#jprm-existing-section').val(),10)||0;
    if(!state.currentMenu) return toast('Select a Menu first.');
    if(!sectionId) return toast('Choose an existing Section first.');
    setLoading(true);
    apiPost('menu-builder/section/attach',{menu_id:state.currentMenu,section_id:sectionId})
      .done(()=>chainLoadAndRender(true))
      .fail(x=>toast(apiFailToMessage(x),'error'))
      .always(()=>setLoading(false));
  });

  $('#jprm-save').on('click',function(){
    if(!state.currentMenu) return toast('Select a Menu first.');

    const tree = buildTreeFromDOM();
    const itemsPayload = buildItemsPayloadFromDOM();

    setLoading(true);
    apiPost('menu-builder/sections/order',{tree,menu_id:state.currentMenu})
      .then(()=>apiPost('menu-builder/items/order',{menu_id:state.currentMenu,items:itemsPayload}))
      .then(function(res){
        const msg = (res && res.msg) ? res.msg : 'Menu layout saved.';
        toast(msg); // info style
        return chainLoadAndRender(true);
      })
      .fail(x=>toast(apiFailToMessage(x), 'error'))
      .always(()=>setLoading(false));
  });


  $(document).on('change','#jprm-unassigned-all',function(){ $('#jprm-unassigned-list input[type="checkbox"]').prop('checked', $(this).is(':checked')); });
  $('#jprm-assign-item').on('click',function(){
    if(!state.currentMenu) return toast('Select a Menu first.');
    const secId=parseInt($('#jprm-item-target-section').val(),10)||0;
    const ids=$('#jprm-unassigned-list input[type="checkbox"]:checked').map(function(){return parseInt($(this).attr('data-id'),10);}).get();
    if(!secId||!ids.length) return toast('Choose a section and at least one item.');
    setLoading(true);
    apiPost('menu-builder/item/assign-batch',{menu_id:state.currentMenu,section_id:secId,ids})
      .done(()=>chainLoadAndRender(false))
      .fail(x=>toast(apiFailToMessage(x)))
      .always(()=>setLoading(false));
  });

  $(function(){ loadMenus().then(()=>loadSections()).then(()=>loadAvailableSections()).then(()=>loadItems()).then(()=>loadUnassigned()).then(()=>{ renderList(); expandAll(); }); });
})(jQuery);
