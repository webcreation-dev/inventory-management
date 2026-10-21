$(function () {
  "use strict";

  var $input = $('#flash_sale_item_search');
  var $results = $('#flash_sale_item_results');
  var $cartBody = $('#flashSaleCartBody');
  if (!$input.length || !$results.length || !$cartBody.length) {
    return;
  }

  var searchUrl = $input.attr('data-search-url') || '';
  var labels = window.flashSaleLabels || {};
  var optionalLabel = labels.optional || 'Optional';
  var emptyLabel = labels.empty || 'No products added yet.';
  var noResultsLabel = labels.noResults || 'No products found';
  var searchingLabel = labels.searching || 'Searching…';
  var initialItems = Array.isArray(window.flashSaleInitialItems) ? window.flashSaleInitialItems.slice() : [];
  var searchTimer = null;
  var lastQuery = null;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function selectedIds() {
    return $cartBody.find('tr[data-item-id]').map(function () {
      return String($(this).data('item-id'));
    }).get();
  }

  function toggleEmptyRow() {
    var hasItems = $cartBody.find('tr[data-item-id]').length > 0;
    $cartBody.find('.flash-cart-empty').remove();
    if (!hasItems) {
      $cartBody.append(
        '<tr class="flash-cart-empty"><td colspan="4" class="text-center text-muted py-4">' +
          escapeHtml(emptyLabel) +
        '</td></tr>'
      );
    }
  }

  function addToCart(item) {
    if (!item || !item.id) {
      return;
    }
    if ($cartBody.find('tr[data-item-id="' + item.id + '"]').length) {
      return;
    }

    $cartBody.find('.flash-cart-empty').remove();

    var name = escapeHtml(item.name || item.text || '');
    var code = item.code ? ' <small class="text-muted">(' + escapeHtml(item.code) + ')</small>' : '';
    var price = escapeHtml(item.sale_price_formatted || item.sale_price || '-');
    var id = escapeHtml(item.id);

    $cartBody.append(
      '<tr data-item-id="' + id + '">' +
        '<td>' +
          '<input type="hidden" name="item_ids[]" value="' + id + '">' +
          '<span class="fw-medium">' + name + '</span>' + code +
        '</td>' +
        '<td>' + price + '</td>' +
        '<td>' +
          '<input type="number" step="0.001" min="0" class="form-control form-control-sm" ' +
            'name="flash_prices[' + id + ']" value="" placeholder="' + escapeHtml(optionalLabel) + '">' +
        '</td>' +
        '<td class="text-end">' +
          '<button type="button" class="btn btn-sm btn-icon btn-outline-danger remove-flash-cart-item" title="Remove">' +
            '<i class="ti tabler-trash"></i>' +
          '</button>' +
        '</td>' +
      '</tr>'
    );

    // Keep local initial list in sync so already-added products disappear.
    initialItems = initialItems.filter(function (row) {
      return String(row.id) !== String(item.id);
    });
  }

  function renderResults(items) {
    var exclude = selectedIds();
    var filtered = (items || []).filter(function (item) {
      return exclude.indexOf(String(item.id)) === -1;
    });

    if (!filtered.length) {
      $results.html(
        '<div class="dropdown-item text-muted disabled">' + escapeHtml(noResultsLabel) + '</div>'
      ).show();
      return;
    }

    var html = filtered.map(function (item) {
      var label = escapeHtml(item.text || item.name || '');
      var price = escapeHtml(item.sale_price_formatted || item.sale_price || '');
      return (
        '<button type="button" class="dropdown-item flash-sale-result-item d-flex justify-content-between align-items-center" ' +
          'data-id="' + escapeHtml(item.id) + '">' +
          '<span>' + label + '</span>' +
          '<small class="text-muted ms-2">' + price + '</small>' +
        '</button>'
      );
    }).join('');

    $results.html(html).show();

    $results.find('.flash-sale-result-item').each(function () {
      var id = String($(this).data('id'));
      var match = filtered.find(function (row) { return String(row.id) === id; });
      $(this).data('item', match || null);
    });
  }

  function showInitial() {
    lastQuery = '';
    renderResults(initialItems);
  }

  function fetchItems(query) {
    if (!searchUrl) {
      renderResults(initialItems);
      return;
    }

    $results.html(
      '<div class="dropdown-item text-muted disabled">' + escapeHtml(searchingLabel) + '</div>'
    ).show();

    $.ajax({
      url: searchUrl,
      method: 'GET',
      dataType: 'json',
      data: {
        q: query || '',
        exclude: selectedIds().join(',')
      },
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).done(function (response) {
      var rows = (response && Array.isArray(response.results)) ? response.results : [];
      if (!query) {
        initialItems = rows.slice();
      }
      renderResults(rows);
    }).fail(function (xhr) {
      console.error('Flash sale product search failed', xhr && xhr.status, xhr && xhr.responseText);
      if (!query && initialItems.length) {
        renderResults(initialItems);
        return;
      }
      $results.html(
        '<div class="dropdown-item text-danger disabled">' + escapeHtml(noResultsLabel) + '</div>'
      ).show();
    });
  }

  function hideResultsSoon() {
    setTimeout(function () {
      if (!$input.is(':focus') && !$results.is(':hover')) {
        $results.hide();
      }
    }, 150);
  }

  $input.on('focus click', function () {
    var q = $.trim($input.val() || '');
    if (q === '' && initialItems.length) {
      showInitial();
      return;
    }
    fetchItems(q);
  });

  $input.on('input', function () {
    var q = $.trim($input.val() || '');
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      if (q === lastQuery && $results.is(':visible')) {
        return;
      }
      lastQuery = q;
      if (q === '' && initialItems.length) {
        showInitial();
        return;
      }
      fetchItems(q);
    }, 200);
  });

  $input.on('keydown', function (e) {
    if (e.key === 'Escape') {
      $results.hide();
    }
  });

  $results.on('mousedown', '.flash-sale-result-item', function (e) {
    e.preventDefault();
    var item = $(this).data('item');
    if (!item) {
      return;
    }
    addToCart(item);
    $input.val('');
    lastQuery = '';
    $results.hide();
  });

  $input.on('blur', hideResultsSoon);
  $results.on('mouseleave', hideResultsSoon);

  $(document).on('click', '.remove-flash-cart-item', function () {
    $(this).closest('tr').remove();
    toggleEmptyRow();
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('#flashSaleItemPicker').length) {
      $results.hide();
    }
  });
});
