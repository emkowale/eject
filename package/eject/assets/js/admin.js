/*
 * File: assets/js/admin.js
 * Description: Admin interactions (queue scan, buttons, runs, settings).
 * Plugin: Eject
 * Author: Eric Kowalewski
 * Last Updated: 2025-11-04 EDT
 */
(function($){
  // ---- mini helpers ---------------------------------------------------------
  function busy($btn, on){
    var $sp = $btn.find('.spinner');
    if(on){ $btn.prop('disabled', true).addClass('is-busy'); $sp.addClass('is-active'); }
    else  { $btn.prop('disabled', false).removeClass('is-busy'); $sp.removeClass('is-active'); }
  }
  function ajaxPost(action, data, $btn){
    data = data || {};
    data.action = action;
    var n = $btn && $btn.data('nonce');
    if(!n && window.EJECT_QUEUE && window.EJECT_QUEUE.nonce) n = window.EJECT_QUEUE.nonce;
    if(n) data._wpnonce = n;
    if($btn) busy($btn, true);
    return $.post(ajaxurl, data).always(function(){ if($btn) busy($btn, false); });
  }
  function downloadJson(filename, obj){
    var blob = new Blob([JSON.stringify(obj, null, 2)], {type:'application/json'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a'); a.href = url; a.download = filename; a.click();
    setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
  }
  function removeRowAndRescan($tr){
    $tr.addClass('eject-row-removing').fadeOut(120, function(){
      $(this).remove();
      ajaxPost('eject_scan_orders', {}, null).done(function(resp){
        renderQueue(resp && resp.success ? (resp.data.items || []) : []);
      });
    });
  }

  // ---- QUEUE ---------------------------------------------------------------
  function renderQueue(items){
    var $tb = $('#eject-queue-table tbody'); $tb.empty();
    if(!items || !items.length){
      $tb.append('<tr class="eject-empty"><td></td><td colspan="8">✅ All current Processing orders have been assigned to vendor runs.</td></tr>');
      return;
    }
    window.EJECT_QUEUE = window.EJECT_QUEUE || {};
    var nonceAttr = (window.EJECT_QUEUE && window.EJECT_QUEUE.nonce)
      ? ' data-nonce="'+window.EJECT_QUEUE.nonce+'"' : '';
    window.EJECT_QUEUE.items = items;

    items.forEach(function(r, i){
      var vc = r.vendor + '(' + r.vendor_item + ')';
      var row =
        '<tr data-idx="'+i+'">' +
          '<td><input type="checkbox" class="eject-q-chk"></td>' +
          '<td>#'+r.order_id+'</td>' +
          '<td>'+(r.customer||'')+'</td>' +
          '<td>'+vc+'</td>' +
          '<td>'+(r.item||'')+'</td>' +
          '<td>'+(r.color||'N/A')+'</td>' +
          '<td>'+(r.size||'N/A')+'</td>' +
          '<td>'+(r.qty||0)+'</td>' +
          '<td>' +
            '<button class="button eject-add-one"'+nonceAttr+'>Add <span class="spinner"></span></button> ' +
            '<button class="button eject-dismiss-one"'+nonceAttr+'>Dismiss <span class="spinner"></span></button>' +
          '</td>' +
        '</tr>';
      $tb.append(row);
    });
  }

  $(document).on('click', '.eject-dismiss-one', function(e){
    e.preventDefault();
    var $b=$(this), $tr=$b.closest('tr'), idx=+$tr.data('idx');
    var r=(window.EJECT_QUEUE&&window.EJECT_QUEUE.items)?window.EJECT_QUEUE.items[idx]:null;
    if(!r) return;
    ajaxPost('eject_dismiss_item',{order_id:r.order_id,item_id:r.item_id},$b).done(function(){
      removeRowAndRescan($tr);
    });
  });

  $(document).on('click', '#eject-queue-dismiss-selected', function(e){
    e.preventDefault();
    var $b=$(this), items=[], all=(window.EJECT_QUEUE||{}).items||[];
    $('#eject-queue-table tbody tr').each(function(){
      var $tr=$(this);
      if($tr.find('.eject-q-chk').prop('checked')){
        var idx=+$tr.data('idx'), r=all[idx];
        if(r) items.push({order_id:r.order_id,item_id:r.item_id});
      }
    });
    if(!items.length){ alert('Select at least one row.'); return; }
    ajaxPost('eject_dismiss_bulk',{items:JSON.stringify(items)},$b).done(function(){
      $('#eject-queue-table tbody tr').each(function(){
        var $tr=$(this); if($tr.find('.eject-q-chk').prop('checked')) $tr.remove();
      });
      ajaxPost('eject_scan_orders',{},null).done(function(resp){
        renderQueue(resp&&resp.success?(resp.data.items||[]):[]);
      });
    });
  });

  $(document).on('click', '.eject-add-one', function(e){
    e.preventDefault();
    var $b=$(this), $tr=$b.closest('tr'), idx=+$tr.data('idx');
    var r=(window.EJECT_QUEUE&&window.EJECT_QUEUE.items)?window.EJECT_QUEUE.items[idx]:null;
    if(!r) return;
    ajaxPost('eject_add_to_run',{
      order_id:r.order_id, item_id:r.item_id, vendor:r.vendor,
      item:r.item, color:r.color, size:r.size, qty:r.qty
    },$b).done(function(){
      removeRowAndRescan($tr);
    });
  });

  $(document).on('change', '#eject-q-all', function(){ $('.eject-q-chk').prop('checked', this.checked); });

  // Bulk add — keep spinner until redirect to Runs (server merges items)
  $(document).on('click', '#eject-queue-add-selected', function(e){
    e.preventDefault();
    var $b=$(this), $sp=$b.find('.spinner');
    var items=[], all=(window.EJECT_QUEUE||{}).items||[];
    $('#eject-queue-table tbody tr').each(function(){
      var $tr=$(this);
      if($tr.find('.eject-q-chk').prop('checked')){
        var idx=+$tr.data('idx'), r=all[idx];
        if(r) items.push({
          order_id:r.order_id, order_item_id:r.item_id, vendor:r.vendor,
          item:r.item, color:r.color, size:r.size, qty:r.qty
        });
      }
    });
    if(!items.length){ alert('Select at least one row.'); return; }

    $b.prop('disabled',true).addClass('is-busy'); $sp.addClass('is-active');
    var data={action:'eject_add_to_run',bulk:1,items:JSON.stringify(items)};
    var n=(window.EJECT_QUEUE&&EJECT_QUEUE.nonce); if(n) data._wpnonce=n;

    $.post(ajaxurl,data).done(function(resp){
      if(resp && resp.success){ window.location='admin.php?page=eject-runs'; }
      else { alert('Add failed.'); $b.prop('disabled',false).removeClass('is-busy'); $sp.removeClass('is-active'); }
    }).fail(function(){
      alert('Add failed.'); $b.prop('disabled',false).removeClass('is-busy'); $sp.removeClass('is-active');
    });
  });

  $(function(){
    if($('#eject-queue-table').length){
      ajaxPost('eject_scan_orders',{},null).done(function(resp){
        renderQueue(resp&&resp.success?(resp.data.items||[]):[]);
      });
    }
  });

  $(document).on('click', '#eject-export-pos', function(e){
    e.preventDefault();
    var $b=$(this);
    ajaxPost('eject_export_pos', {}, $b).done(function(resp){
      if(resp && resp.success && resp.data && resp.data.data){
        downloadJson('eject-pos.json', resp.data.data);
      } else { alert('Export failed.'); }
    });
  });

})(jQuery);

// ---- SETTINGS: maintenance buttons -----------------------------------------
jQuery(function($){
  $(document).on('click', '#eject-clear-runs', function(e){
    e.preventDefault(); var $b=$(this);
    ajaxPost('eject_clear_runs', {}, $b).done(function(){ location.reload(); });
  });
  $(document).on('click', '#eject-clear-exc', function(e){
    e.preventDefault(); var $b=$(this);
    ajaxPost('eject_clear_exceptions', {}, $b).done(function(){ alert('Exceptions cleared'); });
  });
  $(document).on('click', '#eject-unsuppress-queue', function(e){
    e.preventDefault(); var $b=$(this);
    ajaxPost('eject_unsuppress_queue', {}, $b).done(function(){ location.reload(); });
  });
  function ajaxPost(action, data, $btn){
    data=data||{}; data.action=action;
    var n=$btn&&$btn.data('nonce');
    if(!n&&window.EJECT_QUEUE&&window.EJECT_QUEUE.nonce) n=EJECT_QUEUE.nonce;
    if(n) data._wpnonce=n;
    if($btn){ var $sp=$btn.find('.spinner'); $btn.prop('disabled',true).addClass('is-busy'); $sp.addClass('is-active'); }
    return $.post(ajaxurl,data).always(function(){
      if($btn){ var $sp=$btn.find('.spinner'); $btn.prop('disabled',false).removeClass('is-busy'); $sp.removeClass('is-active'); }
    });
  }
});

// ---- RUNS: Mark Ordered / Not Ordered -> redirect to POs -------------------
jQuery(function($){
  function ejBusy($btn,on){ var $sp=$btn.find('.spinner'); if(on){$btn.prop('disabled',true).addClass('is-busy');$sp.addClass('is-active');}else{$btn.prop('disabled',false).removeClass('is-busy');$sp.removeClass('is-active');}}
  function ejPost(action,data,$btn){ data=data||{}; data.action=action; var n=$btn.data('nonce'); if(!n&&window.EJECT_QUEUE&&EJECT_QUEUE.nonce) n=EJECT_QUEUE.nonce; if(n) data._wpnonce=n; ejBusy($btn,true); return jQuery.post(ajaxurl,data); }

  // Mark Ordered: publish run, then go straight to POs and highlight the new one
  $(document).on('click','.eject-mark-ordered',function(e){
    e.preventDefault();
    var $b=$(this), $card=$b.closest('.eject-vendor-card');
    ejPost('eject_mark_ordered',{
      po_id: $card.data('po-id'),
      vendor: $card.data('vendor')
    },$b).done(function(resp){
      if(resp && resp.success && resp.data && resp.data.po_id){
        window.location = 'admin.php?page=eject-pos&new_po=' + encodeURIComponent(resp.data.po_id);
      } else { ejBusy($b,false); alert('Mark Ordered failed.'); }
    }).fail(function(){ ejBusy($b,false); alert('Mark Ordered failed.'); });
  });

  $(document).on('click','.eject-mark-not-ordered',function(e){
    e.preventDefault();
    var $b=$(this), $card=$b.closest('.eject-vendor-card');
    ejPost('eject_mark_not_ordered',{ po_id:$card.data('po-id') },$b).done(function(resp){
      if(!(resp && resp.success)) { ejBusy($b,false); alert('Set Not Ordered failed.'); }
      else location.reload();
    }).fail(function(){ ejBusy($b,false); alert('Set Not Ordered failed.'); });
  });
});
