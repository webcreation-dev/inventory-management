$(async function () {
  "use strict";
  /** #################### -- The base JS part should be on top of all JS files --  #################### **/
  // Language Translator
  let base_url = $('#base_url').val();
  let language_name = $('#language_name').val();
  let language_path = '/resources/lang/' + language_name + '.json';
  let language_file = base_url + language_path;
  let language_key = {};

  async function loadLanguage() {
    try {
      const res = await fetch(language_file);
      language_key = await res.json();
    } catch (err) {
      console.error('Failed to load language file:', err);
    }
  }
  await loadLanguage();


  // Company Info
  let company_data = $('#company_data').val();
  let company_session_data = {};
  try {
      company_session_data = JSON.parse(company_data);
  } catch (e) {
      console.error('Error parsing company info:', e);
  }

  function getCompanyDisplayName() {
    if (!company_session_data || typeof company_session_data !== 'object') {
      return '';
    }
    return (
      company_session_data.business_name ||
      company_session_data.short_name ||
      ''
    ).toString().trim();
  }

  function getPriceDecimals() {
    const p = company_session_data && company_session_data.precision;
    const n = parseInt(p, 10);
    return Number.isFinite(n) && n >= 0 ? n : 2;
  }

  /**
   * Match PHP formatAmount() in app/Helpers/GlobalHelper.php (session company)
   */
  function formatAmountLikePhp(amount) {
    if (!company_session_data || typeof company_session_data !== 'object') {
      const n = parseFloat(amount);
      return Number.isFinite(n) ? String(n) : '0';
    }
    const num = parseFloat(amount);
    const safe = Number.isFinite(num) ? num : 0;
    const p = getPriceDecimals();
    const currency = (company_session_data.currency ?? '').toString();
    const currencyPosition = company_session_data.currency_position ?? 'Before Amount';
    let thousandSep = company_session_data.thousands_separator ?? ',';
    let decimalSep = company_session_data.decimals_separator ?? '.';
    if (thousandSep === 'space') thousandSep = ' ';
    if (decimalSep === 'space') decimalSep = ' ';

    const negative = safe < 0;
    const absVal = Math.abs(safe);
    const fixed = absVal.toFixed(p);
    const parts = fixed.split('.');
    const intRaw = parts[0] || '0';
    const decPart = parts.length > 1 ? parts[1] : '';
    const withThousands = intRaw.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
    const intFormatted = negative ? '-' + withThousands : withThousands;
    const formatted = p > 0 ? intFormatted + decimalSep + decPart : intFormatted;

    if (currencyPosition === 'Before Amount') {
      return currency + formatted;
    }
    return formatted + currency;
  }

  function formatPriceLine(amount) {
    return 'Price: ' + formatAmountLikePhp(amount);
  }

  function getTextStyleConfig() {
    const lineHeight = parseFloat($('#modalTextLineHeight').val()) || 1.2;
    return { lineHeight };
  }

  function togglePrintModeControls() {
    const printMode = $('#modalPrintMode').val() || 'label';
    if (printMode === 'multiple') {
      $('#modalMultiplePerPageWrap').show();
    } else {
      $('#modalMultiplePerPageWrap').hide();
    }
  }

  function applyBarcodeSettings(settings) {
    if (!settings || typeof settings !== 'object') return;

    $('#modalLabelWidth').val(settings.label_width ?? 50);
    $('#modalLabelHeight').val(settings.label_height ?? 30);
    $('#modalBarcodeHeight').val(settings.barcode_height ?? 25);
    $('#modalBarcodeWidth').val(settings.barcode_width ?? 2);
    $('#modalBarcodeFontSize').val(settings.barcode_font_size ?? 12);
    $('#modalCompanyNameFontSize').val(settings.company_font_size ?? 14);
    $('#modalItemNameFontSize').val(settings.item_font_size ?? 14);
    $('#modalPriceFontSize').val(settings.price_font_size ?? 14);
    $('#modalCompanyNameFontWeight').val((settings.company_font_weight ?? settings.font_weight ?? '700').toString());
    $('#modalItemNameFontWeight').val((settings.item_font_weight ?? settings.font_weight ?? '500').toString());
    $('#modalPriceFontWeight').val((settings.price_font_weight ?? settings.font_weight ?? '400').toString());
    $('#modalTextLineHeight').val(settings.line_height ?? 1.2);
    $('#modalPrintMode').val(settings.print_mode ?? 'label');
    $('#modalMultiplePerPage').val(settings.multiple_per_page ?? 12);
    $('#modalShowCompanyName').prop('checked', Boolean(settings.show_company_name ?? true));
    $('#modalShowItemName').prop('checked', Boolean(settings.show_item_name ?? true));
    $('#modalShowPrice').prop('checked', Boolean(settings.show_price ?? true));
    togglePrintModeControls();
  }

  function collectBarcodeSettings() {
    return {
      label_width: parseFloat($('#modalLabelWidth').val()) || 50,
      label_height: parseFloat($('#modalLabelHeight').val()) || 30,
      barcode_height: parseInt($('#modalBarcodeHeight').val(), 10) || 25,
      barcode_width: parseFloat($('#modalBarcodeWidth').val()) || 2,
      barcode_font_size: parseInt($('#modalBarcodeFontSize').val(), 10) || 12,
      company_font_size: parseInt($('#modalCompanyNameFontSize').val(), 10) || 14,
      item_font_size: parseInt($('#modalItemNameFontSize').val(), 10) || 14,
      price_font_size: parseInt($('#modalPriceFontSize').val(), 10) || 14,
      company_font_weight: ($('#modalCompanyNameFontWeight').val() || '700').toString(),
      item_font_weight: ($('#modalItemNameFontWeight').val() || '500').toString(),
      price_font_weight: ($('#modalPriceFontWeight').val() || '400').toString(),
      line_height: parseFloat($('#modalTextLineHeight').val()) || 1.2,
      print_mode: $('#modalPrintMode').val() || 'label',
      multiple_per_page: parseInt($('#modalMultiplePerPage').val(), 10) || 12,
      show_company_name: $('#modalShowCompanyName').is(':checked') ? 1 : 0,
      show_item_name: $('#modalShowItemName').is(':checked') ? 1 : 0,
      show_price: $('#modalShowPrice').is(':checked') ? 1 : 0,
      show_barcode: 1
    };
  }

  let barcodeSaveTimer = null;

  function saveBarcodeSettingsToServer(showSuccessAlert) {
    const saveUrl = route('item.barcode-settings.save', {}, false, Ziggy);
    const payload = collectBarcodeSettings();
    $.ajax({
      url: base_url + saveUrl,
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: payload,
      dataType: 'json',
      success: function(response) {
        if (showSuccessAlert && response && response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: response.message || 'Barcode settings saved.',
            timer: 1500,
            showConfirmButton: false
          });
        }
      },
      error: function(xhr) {
        if (showSuccessAlert) {
          const msg = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Unable to save barcode settings.';
          Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: msg
          });
        }
      }
    });
  }

  function scheduleBarcodeSettingsSave() {
    clearTimeout(barcodeSaveTimer);
    barcodeSaveTimer = setTimeout(function() {
      saveBarcodeSettingsToServer(false);
    }, 450);
  }

  function loadBarcodeDefaults() {
    const url = route('item.barcode-settings', {}, false, Ziggy);
    $.ajax({
      url: base_url + url,
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        if (response && response.status === 'success' && response.data) {
          applyBarcodeSettings(response.data);
          updateBarcodeModalPreview();
        }
      }
    });
  }

  /** #################### -- The base JS part should be on top of all JS files --  #################### **/


  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/item',
        type: 'GET',
        error: function(xhr, error, thrown) {
          window.location.reload();
        }
      },
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'type' },
        { data: 'category_id' },
        { data: 'purchase_price' },
        { data: 'sale_price' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const show_url = route('item.show', { item: data.encrypted_id }, false, Ziggy);
            const edit_url = route('item.edit', { item: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('item.destroy', { item: data.encrypted_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action">
              
              <button type="button" data-item-id="${data.encrypted_id}" data-item-name="${data.name || ''}" data-item-code="${data.code || ''}" data-item-price="${data.sale_price_raw || 0}" data-item-type="${data.type || ''}" class="btn btn-sm btn-icon print-label-btn" title="Print Label">
                <i class="ti tabler-barcode"></i>
              </button>
              
              <a href="${base_url+show_url}" class="btn btn-sm btn-icon" title="View">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="${base_url+edit_url}" class="btn btn-sm btn-icon edit-record" title="Edit">
                <i class="ti tabler-edit"></i>
              </a>
              <button type="button" data-id="${ data.actual_id }" class="delete_data btn btn-sm btn-icon delete-record" title="Delete">
                <i class="ti tabler-trash delete"></i>
              </button>
              <form id="deleteForm-${ data.actual_id }" action="${ base_url+destroy_url }" method="POST" style="display: none;">
                <input type="hidden" name="_token" value="${ csrf }">
                <input type="hidden" name="_method" value="DELETE">
              </form>
            </div>`;
          }
        }
      ],
      dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
          '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-primary dropdown-toggle me-2',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            }
          ]
        }, 
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Item']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/item/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }

  // <button type="button" data-item-id="${data.encrypted_id}" data-item-name="${data.name || ''}" data-item-code="${data.code || ''}" data-item-price="${data.sale_price_raw || 0}" data-item-type="${data.type || ''}" class="btn btn-sm btn-icon print-barcode-btn" title="Print Barcode">
  //               <i class="ti tabler-barcode"></i>
  //             </button>

  // Store selected items for barcode printing
  let selectedItemsForPrint = [];
  let currentItemType = '';
  let currentVariations = [];
  let originalItemData = null;

  // Handle Print Label button click
  $(document).on('click', '.print-label-btn', function() {
    const $btn = $(this);
    const itemData = {
      id: $btn.data('item-id'),
      name: $btn.data('item-name') || '',
      code: $btn.data('item-code') || '',
      price: parseFloat($btn.data('item-price')) || 0,
      type: $btn.data('item-type') || ''
    };
    
    // Normalize type (handle both "Variation Product" and "Variation_Product")
    const normalizedType = itemData.type.replace(/\s+/g, '_');
    currentItemType = normalizedType;
    originalItemData = JSON.parse(JSON.stringify(itemData)); // Deep copy
    selectedItemsForPrint = [itemData];
    
    // Set item data in modal
    $('#barcodeModalItemName').text(itemData.name || 'N/A');
    $('#barcodeModalItemCode').text(itemData.code || 'N/A');
    $('#barcodeModalItemPrice').text(formatPriceLine(itemData.price));
    
    // Show modal first
    $('#barcodePrintModal').modal('show');
    
    // Fetch variations if Variation_Product
    if (normalizedType === 'Variation_Product') {
      fetchItemVariations(itemData.id, function(variations) {
        currentVariations = variations || [];
        setupVariationSelect();
        // Update preview after modal is shown
        setTimeout(function() {
          updateBarcodeModalPreview();
        }, 300);
      });
    } else {
      currentVariations = [];
      setupVariationSelect();
      // Update preview after modal is shown
      setTimeout(function() {
        updateBarcodeModalPreview();
      }, 300);
    }
  });

  // Handle Print Barcode button click
  $(document).on('click', '.print-barcode-btn', function() {
    const $btn = $(this);
    const itemData = {
      id: $btn.data('item-id'),
      name: $btn.data('item-name') || '',
      code: $btn.data('item-code') || '',
      price: parseFloat($btn.data('item-price')) || 0,
      type: $btn.data('item-type') || ''
    };
    
    // Normalize type (handle both "Variation Product" and "Variation_Product")
    const normalizedType = itemData.type.replace(/\s+/g, '_');
    currentItemType = normalizedType;
    originalItemData = JSON.parse(JSON.stringify(itemData)); // Deep copy
    selectedItemsForPrint = [itemData];
    
    // Set item data in modal
    $('#barcodeModalItemName').text(itemData.name || 'N/A');
    $('#barcodeModalItemCode').text(itemData.code || 'N/A');
    $('#barcodeModalItemPrice').text(formatPriceLine(itemData.price));
    
    // Show modal first
    $('#barcodePrintModal').modal('show');
    
    // Fetch variations if Variation_Product
    if (normalizedType === 'Variation_Product') {
      fetchItemVariations(itemData.id, function(variations) {
        currentVariations = variations || [];
        setupVariationSelect();
        // Update preview after modal is shown
        setTimeout(function() {
          updateBarcodeModalPreview();
        }, 300);
      });
    } else {
      currentVariations = [];
      setupVariationSelect();
      // Update preview after modal is shown
      setTimeout(function() {
        updateBarcodeModalPreview();
      }, 300);
    }
  });

  // Fetch item variations via AJAX
  function fetchItemVariations(itemEncryptedId, callback) {
    const variationsUrl = route('item.variations', { item: itemEncryptedId }, false, Ziggy);
    $.ajax({
      url: base_url + variationsUrl,
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        if (typeof callback === 'function') {
          callback(response.variations || []);
        }
      },
      error: function() {
        if (typeof callback === 'function') {
          callback([]);
        }
      }
    });
  }

  // Setup variation select dropdown
  function setupVariationSelect() {
    const variationSelect = $('#modalVariationSelect');
    const container = $('#variationSelectContainer');
    
    // Normalize currentItemType for comparison (it should already be normalized, but double-check)
    const normalizedType = currentItemType ? currentItemType.replace(/\s+/g, '_') : '';
    
    if (normalizedType === 'Variation_Product' && currentVariations && currentVariations.length > 0) {
      container.show();
      variationSelect.empty();
      variationSelect.append('<option value="">Use Parent Item</option>');
      
      currentVariations.forEach(function(variation) {
        variationSelect.append(`<option value="${variation.item_code}" data-name="${variation.variation_name}" data-price="${variation.sale_price || 0}">${variation.variation_name} (${variation.item_code})</option>`);
      });
    } else {
      container.hide();
      variationSelect.empty();
      variationSelect.append('<option value="">Use Parent Item</option>');
    }
  }

  // Handle variation selection change
  $(document).on('change', '#modalVariationSelect', function() {
    const selectedOption = $(this).find('option:selected');
    if (selectedOption.val() && selectedItemsForPrint.length > 0) {
      const itemData = selectedItemsForPrint[0];
      itemData.code = selectedOption.val();
      itemData.name = selectedOption.data('name') || itemData.name;
      itemData.price = parseFloat(selectedOption.data('price')) || itemData.price;
      
      // Update modal display
      $('#barcodeModalItemName').text(itemData.name || 'N/A');
      $('#barcodeModalItemCode').text(itemData.code || 'N/A');
      $('#barcodeModalItemPrice').text(formatPriceLine(itemData.price));
      
      updateBarcodeModalPreview();
    } else {
      // Reset to parent item
      if (originalItemData && selectedItemsForPrint.length > 0) {
        const itemData = selectedItemsForPrint[0];
        itemData.code = originalItemData.code;
        itemData.name = originalItemData.name;
        itemData.price = originalItemData.price;
        
        $('#barcodeModalItemName').text(itemData.name || 'N/A');
        $('#barcodeModalItemCode').text(itemData.code || 'N/A');
        $('#barcodeModalItemPrice').text(formatPriceLine(itemData.price));
        
        updateBarcodeModalPreview();
      }
    }
  });

  // Update barcode preview in modal
  function updateBarcodeModalPreview() {
    if (selectedItemsForPrint.length === 0) return;
    
    const itemData = selectedItemsForPrint[0];
    const showCompanyName = $('#modalShowCompanyName').is(':checked');
    const showItemName = $('#modalShowItemName').is(':checked');
    const showPrice = $('#modalShowPrice').is(':checked');
    const showBarcode = true;
    
    const labelWidthMm = parseFloat($('#modalLabelWidth').val()) || 50;
    const labelHeightMm = parseFloat($('#modalLabelHeight').val()) || 30;
    const companyNameFontSize = $('#modalCompanyNameFontSize').val() || 11;
    const itemNameFontSize = $('#modalItemNameFontSize').val() || 14;
    const priceFontSize = $('#modalPriceFontSize').val() || 14;
    const barcodeFontSize = $('#modalBarcodeFontSize').val() || 12;
    const barcodeWidth = parseFloat($('#modalBarcodeWidth').val()) || 2;
    const barcodeHeight = parseInt($('#modalBarcodeHeight').val(), 10) || 50;
    const textStyle = getTextStyleConfig();
    const companyNameFontWeight = ($('#modalCompanyNameFontWeight').val() || '700').toString();
    const itemNameFontWeight = ($('#modalItemNameFontWeight').val() || '500').toString();
    const priceFontWeight = ($('#modalPriceFontWeight').val() || '400').toString();
    
    const companyName = getCompanyDisplayName();
    const itemName = itemData.name || 'N/A';
    const itemCode = itemData.code || '';
    const itemPrice = isNaN(itemData.price) ? 0 : parseFloat(itemData.price);
    
    let inner = '';
    if (showCompanyName && companyName) {
      inner += `<div style="font-size: ${companyNameFontSize}px; font-weight: ${companyNameFontWeight}; line-height: ${textStyle.lineHeight}; margin: 0; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${companyName}</div>`;
    }
    if (showItemName) {
      inner += `<div style="font-size: ${itemNameFontSize}px; font-weight: ${itemNameFontWeight}; line-height: ${textStyle.lineHeight}; margin: 0; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${itemName}</div>`;
    }
    if (showBarcode && itemCode) {
      inner += `<svg id="barcode-modal-svg"></svg>`;
    }
    if (showPrice) {
      inner += `<div style="font-size: ${priceFontSize}px; font-weight: ${priceFontWeight}; line-height: ${textStyle.lineHeight}; margin: 0; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${formatPriceLine(itemPrice)}</div>`;
    }
    
    const html = `
      <div class="barcode-preview-surface" style="width: ${labelWidthMm}mm; min-height: ${labelHeightMm}mm; padding: 0; box-sizing: border-box;">
        <div class="barcode-print-area" style="max-width: 100%; overflow: hidden; margin: 0;">
          ${inner}
        </div>
      </div>`;
    
    $('#barcodeModalPreview').html(html);
    
    if (showBarcode && itemCode) {
      function generateModalBarcode() {
        if (typeof JsBarcode === 'undefined') {
          setTimeout(generateModalBarcode, 100);
          return;
        }
        
        const svgElement = document.getElementById('barcode-modal-svg');
        if (svgElement) {
          try {
            JsBarcode(svgElement, itemCode, {
              format: "CODE128",
              width: barcodeWidth,
              height: barcodeHeight,
              displayValue: true,
              fontSize: parseInt(barcodeFontSize, 10) || 12,
              margin: 0
            });
            svgElement.style.display = 'block';
            svgElement.style.margin = '0 auto';
            svgElement.style.maxWidth = '100%';
          } catch (e) {
            console.error('Barcode generation error:', e);
          }
        } else {
          setTimeout(generateModalBarcode, 50);
        }
      }
      
      setTimeout(generateModalBarcode, 50);
    }
  }

  // Auto-update preview when controls change
  $(document).on('change input', '#modalShowCompanyName, #modalShowItemName, #modalShowPrice, #modalCompanyNameFontSize, #modalItemNameFontSize, #modalPriceFontSize, #modalCompanyNameFontWeight, #modalItemNameFontWeight, #modalPriceFontWeight, #modalBarcodeFontSize, #modalBarcodeHeight, #modalBarcodeWidth, #modalLabelWidth, #modalLabelHeight, #modalTextLineHeight, #modalPrintMode, #modalMultiplePerPage', function() {
    togglePrintModeControls();
    updateBarcodeModalPreview();
    scheduleBarcodeSettingsSave();
  });

  // Regenerate barcode when modal is fully shown
  $('#barcodePrintModal').on('shown.bs.modal', function() {
    setTimeout(function() {
      updateBarcodeModalPreview();
    }, 100);
  });

  // Print Barcode from modal (one label per page)
  $(document).on('click', '#modalPrintBarcode', function() {
    if (selectedItemsForPrint.length === 0) return;
    
    const printWindow = window.open('', '_blank');
    const showCompanyName = $('#modalShowCompanyName').is(':checked');
    const showItemName = $('#modalShowItemName').is(':checked');
    const showPrice = $('#modalShowPrice').is(':checked');
    const showBarcode = true;
    
    const labelWidthMm = parseFloat($('#modalLabelWidth').val()) || 50;
    const labelHeightMm = parseFloat($('#modalLabelHeight').val()) || 30;
    const companyNameFontSize = $('#modalCompanyNameFontSize').val() || 11;
    const itemNameFontSize = $('#modalItemNameFontSize').val() || 14;
    const priceFontSize = $('#modalPriceFontSize').val() || 14;
    const barcodeFontSize = $('#modalBarcodeFontSize').val() || 12;
    const barcodeWidth = parseFloat($('#modalBarcodeWidth').val()) || 2;
    const barcodeHeight = parseInt($('#modalBarcodeHeight').val(), 10) || 50;
    const barcodeQuantity = parseInt($('#modalBarcodeQuantity').val(), 10) || 1;
    const textStyle = getTextStyleConfig();
    const companyNameFontWeight = ($('#modalCompanyNameFontWeight').val() || '700').toString();
    const itemNameFontWeight = ($('#modalItemNameFontWeight').val() || '500').toString();
    const priceFontWeight = ($('#modalPriceFontWeight').val() || '400').toString();
    const printMode = $('#modalPrintMode').val() || 'label';
    const multiplePerPage = parseInt($('#modalMultiplePerPage').val(), 10) || 12;
    const companyName = getCompanyDisplayName();
    
    let html = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Product Barcode</title>
        <style>
          @page { margin: 0; size: auto; }
          html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
          }
          .label-page {
            box-sizing: border-box;
            width: ${labelWidthMm}mm;
            height: ${labelHeightMm}mm;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            page-break-after: always;
            page-break-inside: avoid;
            overflow: hidden;
          }
          .label-page:last-of-type {
            page-break-after: auto;
          }
          .barcode-sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(${labelWidthMm}mm, 1fr));
            gap: 0;
            padding: 0;
          }
          .barcode-tile {
            box-sizing: border-box;
            width: ${labelWidthMm}mm;
            min-height: ${labelHeightMm}mm;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            break-inside: avoid;
          }
          .txt-line {
            margin: 0;
            line-height: ${textStyle.lineHeight};
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
          }
          svg {
            display: block;
            margin: 0 auto;
            max-width: 100%;
          }
        </style>
      </head>
      <body>
    `;
    let barcodeIndex = 0;
    if (printMode === 'label') {
      selectedItemsForPrint.forEach((itemData) => {
        const itemCode = itemData.code || '';
        const itemPrice = isNaN(itemData.price) ? 0 : parseFloat(itemData.price);
        for (let qty = 0; qty < barcodeQuantity; qty++) {
          html += `<div class="label-page">`;
          if (showCompanyName && companyName) {
            html += `<div class="txt-line" style="font-size: ${companyNameFontSize}px; font-weight: ${companyNameFontWeight};">${companyName}</div>`;
          }
          if (showItemName) {
            html += `<div class="txt-line" style="font-size: ${itemNameFontSize}px; font-weight: ${itemNameFontWeight};">${itemData.name || ''}</div>`;
          }
          if (showBarcode && itemCode) {
            html += `<svg id="barcode-product-${barcodeIndex}"></svg>`;
          }
          if (showPrice) {
            html += `<div class="txt-line" style="font-size: ${priceFontSize}px; font-weight: ${priceFontWeight};">${formatPriceLine(itemPrice)}</div>`;
          }
          html += `</div>`;
          barcodeIndex++;
        }
      });
    } else {
      let printed = 0;
      html += `<div class="barcode-sheet">`;
      selectedItemsForPrint.forEach((itemData) => {
        const itemCode = itemData.code || '';
        const itemPrice = isNaN(itemData.price) ? 0 : parseFloat(itemData.price);
        for (let qty = 0; qty < barcodeQuantity; qty++) {
          if (printed > 0 && printed % multiplePerPage === 0) {
            html += `</div><div style="page-break-after: always;"></div><div class="barcode-sheet">`;
          }
          html += `<div class="barcode-tile">`;
          if (showCompanyName && companyName) {
            html += `<div class="txt-line" style="font-size: ${companyNameFontSize}px; font-weight: ${companyNameFontWeight};">${companyName}</div>`;
          }
          if (showItemName) {
            html += `<div class="txt-line" style="font-size: ${itemNameFontSize}px; font-weight: ${itemNameFontWeight};">${itemData.name || ''}</div>`;
          }
          if (showBarcode && itemCode) {
            html += `<svg id="barcode-product-${barcodeIndex}"></svg>`;
          }
          if (showPrice) {
            html += `<div class="txt-line" style="font-size: ${priceFontSize}px; font-weight: ${priceFontWeight};">${formatPriceLine(itemPrice)}</div>`;
          }
          html += `</div>`;
          barcodeIndex++;
          printed++;
        }
      });
      html += `</div>`;
    }
    
    const itemsData = JSON.stringify(selectedItemsForPrint);
    const barcodeConfig = {
      format: "CODE128",
      width: barcodeWidth,
      height: barcodeHeight,
      displayValue: true,
      fontSize: parseInt(barcodeFontSize, 10) || 12,
      margin: 0
    };
    const quantity = barcodeQuantity;
    
    html += `
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
        <script>
          function generateBarcodes() {
            if (typeof JsBarcode === 'undefined') {
              setTimeout(generateBarcodes, 100);
              return;
            }
            
            ${showBarcode ? `var items = ${itemsData};
            var config = ${JSON.stringify(barcodeConfig)};
            var quantity = ${quantity};
            var barcodeIndex = 0;
            items.forEach(function(item) {
              if (item.code) {
                for (var q = 0; q < quantity; q++) {
                  var svgElement = document.getElementById("barcode-product-" + barcodeIndex);
                  if (svgElement) {
                    try {
                      JsBarcode(svgElement, item.code, config);
                    } catch (e) {
                      console.error('Barcode error for item ' + barcodeIndex + ':', e);
                    }
                  }
                  barcodeIndex++;
                }
              }
            });` : ''}
          }
          
          if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(generateBarcodes, 100);
          } else {
            window.addEventListener('load', generateBarcodes);
            document.addEventListener('DOMContentLoaded', generateBarcodes);
          }
        <\/script>
      </body>
      </html>
    `;
    
    printWindow.document.write(html);
    printWindow.document.close();
    
    printWindow.onload = function() {
      setTimeout(function() {
        printWindow.print();
      }, 500);
    };
  });

  togglePrintModeControls();
  loadBarcodeDefaults();

  $(document).on('click', '#modalSetBarcodeDefault', function() {
    clearTimeout(barcodeSaveTimer);
    saveBarcodeSettingsToServer(true);
  });
});
