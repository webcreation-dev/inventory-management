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
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    // Store sale invoice items data
    let saleInvoiceItems = [];
    let rowCounter = 0;

    /** Parse numeric fields that may include thousand separators or stray characters (matches form/company formatting). */
    function parseNumeric(raw) {
        if (raw === undefined || raw === null || raw === '') {
            return 0;
        }
        let s = String(raw).trim();
        s = s.replace(/[^\d.,-]/g, '');
        s = s.replace(/,/g, '');
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : 0;
    }

    /** Message when paid exceeds computed return total (matches inline hint + server validation). */
    const PAID_EXCEEDS_GRAND_TOTAL_MSG = "You can't pay more than Grand Total.";

    // Function to clear validation errors
    function clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.select2-container.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        $('#sale_return_paid_exceeds_error').addClass('d-none');
    }

    // Function to show validation errors from backend
    function showValidationErrors(wrapperSelector, errors) {
        clearValidationErrors();
        $.each(errors, function(field, messages) {
            let input = null;
            let formGroup = null;
            
            // Handle nested array fields
            if (field.includes('.')) {
                let parts = field.split('.');
                let [baseName, index] = field.split('.');
                input = $(`${wrapperSelector} [name="${baseName}[]"]`).eq(parseInt(index));
            } else {
                // Handle regular fields
                input = $(`${wrapperSelector} [name="${field}"]`);
            }
            
            if (input && input.length > 0) {
                formGroup = input.closest('.validate_wrapper');
                if (formGroup.length === 0) {
                    formGroup = input.closest('.mb-3, .mb-5');
                }
                input.addClass('is-invalid');
                
                // For Select2 fields, also add invalid class to the Select2 container
                if (input.hasClass('select2-hidden-accessible')) {
                    input.next('.select2-container').addClass('is-invalid');
                }
                
                if (formGroup.length > 0) {
                    // Remove existing feedback for this field
                    formGroup.find('.invalid-feedback').remove();
                    formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
                } else {
                    // If no wrapper found, append after input or its container
                    let container = input.next('.select2-container');
                    if (container.length > 0) {
                        container.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    } else {
                        input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    }
                }
            }
        });
        $('.invalid-feedback').show();
    }

    // Customer selection change - fetch sale invoices
    $(document).on('change', '#customer_id', function() {
        const customerId = $(this).val();
        const saleInvoiceSelect = $('#sale_id');
        const saleItemSelect = $('#sale_item_id');
        
        // Clear sale invoice and sale items
        saleInvoiceSelect.empty().append('<option value="">Select Sale Invoice</option>');
        saleItemSelect.empty().append('<option value="">Select Sale Item</option>');
        saleInvoiceItems = [];
        $('#saleReturnItems').empty();
        rowCounter = 0;
        updateSummary();
        
        if (!customerId) {
            return;
        }
        
        // Fetch customer sale invoices
        $.ajax({
            type: "GET",
            url: base_url + route('sale-return.get-customer-sale-invoices', {}, false, Ziggy),
            data: { customer_id: customerId },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.success && response.data) {
                    response.data.forEach(function(invoice) {
                        saleInvoiceSelect.append(
                            $('<option></option>')
                                .val(invoice.id)
                                .text(invoice.sale_no + ' - ' + invoice.date + ' (Total: ' + invoice.total_payable + ')')
                        );
                    });
                    
                    // Reinitialize select2
                    if (saleInvoiceSelect.hasClass('select2-hidden-accessible')) {
                        saleInvoiceSelect.select2('destroy');
                    }
                    saleInvoiceSelect.select2({
                        dropdownParent: saleInvoiceSelect.parent(),
                        placeholder: 'Select Sale Invoice'
                    });
                } else {
                    showErrorNotification(response.message || 'No sale invoices found for this customer');
                }
            },
            error: function (xhr) {
                showErrorNotification('Failed to fetch sale invoices');
            }
        });
    });

    // Sale invoice selection change - fetch sale items
    $(document).on('change', '#sale_id', function() {
        const saleId = $(this).val();
        const saleItemSelect = $('#sale_item_id');
        
        // Clear sale items
        saleItemSelect.empty().append('<option value="">Select Sale Item</option>');
        saleInvoiceItems = [];
        
        if (!saleId) {
            return;
        }
        
        // Fetch sale invoice items
        $.ajax({
            type: "GET",
            url: base_url + route('sale-return.get-sale-invoice-items', {}, false, Ziggy),
            data: { sale_id: saleId },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.success && response.data) {
                    saleInvoiceItems = response.data;
                    
                    response.data.forEach(function(item) {
                        const displayText = item.name + (item.code ? ' (' + item.code + ')' : '') + 
                                          ' - Sale Qty: ' + item.sale_quantity + 
                                          ' - Available: ' + item.available_return_quantity;
                        saleItemSelect.append(
                            $('<option></option>')
                                .val(item.id)
                                .text(displayText)
                                .data('item-data', item)
                        );
                    });
                    
                    // Reinitialize select2
                    if (saleItemSelect.hasClass('select2-hidden-accessible')) {
                        saleItemSelect.select2('destroy');
                    }
                    saleItemSelect.select2({
                        dropdownParent: saleItemSelect.parent(),
                        placeholder: 'Select Sale Item'
                    });
                } else {
                    showErrorNotification(response.message || 'No items found in this sale invoice');
                }
            },
            error: function (xhr) {
                showErrorNotification('Failed to fetch sale invoice items');
            }
        });
    });

    // Sale item selection - add to cart
    $(document).on('change', '#sale_item_id', function() {
        const itemId = $(this).val();
        
        if (!itemId) {
            return;
        }
        
        // Get item data from option
        const selectedOption = $(this).find('option:selected');
        const itemData = selectedOption.data('item-data');
        
        if (!itemData) {
            showErrorNotification('Item data not found');
            return;
        }
        
        // Check if item already exists in table
        let itemExists = false;
        $('#saleReturnItems tr').each(function() {
            if ($(this).find('.item-id').val() == itemId) {
                itemExists = true;
                return false; // break
            }
        });
        
        if (itemExists) {
            showErrorNotification('This item is already added to the return list');
            $(this).val('').trigger('change');
            return;
        }
        
        // Check if available return quantity is greater than 0
        if (itemData.available_return_quantity <= 0) {
            showErrorNotification('No available quantity to return for this item');
            $(this).val('').trigger('change');
            return;
        }
        
        // Add item to table
        addItemToTable(itemData);
        
        // Reset select
        $(this).val('').trigger('change');
    });

    // Function to add item to table
    function addItemToTable(itemData) {
        rowCounter++;
        const newRow = $('<tr valign="top"></tr>');
        
        // Determine if IMEI/Serial field should be shown
        const showImeiSerial = itemData.item_type === 'IMEI_Product' || 
                              itemData.item_type === 'Serial_Product' || 
                              itemData.item_type === 'Medicine_Product';
        
        // Set default return quantity to available return quantity
        const defaultReturnQty = Math.min(itemData.available_return_quantity, itemData.sale_quantity);
        
        const unitPriceSale = itemData.unit_price_sale != null ? itemData.unit_price_sale : itemData.unit_price;
        const unitPriceReturn = itemData.unit_price_return != null ? itemData.unit_price_return : itemData.unit_price;
        const prec = (company_session_data && company_session_data.precision != null) ? parseInt(company_session_data.precision, 10) : 2;
        const saleUnitStr = parseNumeric(unitPriceSale).toFixed(prec);
        const returnUnitStr = parseNumeric(unitPriceReturn).toFixed(prec);

        // Create row HTML
        let rowHtml = `
            <td>${rowCounter}</td>
            <td>
                <input type="hidden" class="item-id" name="items[]" value="${itemData.id}">
                <input type="hidden" class="item-type" name="item_types[]" value="${itemData.item_type || 'General_Product'}">
                <input type="hidden" class="sale-quantity" name="sale_quantities[]" value="${itemData.sale_quantity}">
                <span class="item-name">${itemData.name}${itemData.code ? ' (' + itemData.code + ')' : ''}${itemData.brand ? ' - ' + itemData.brand : ''}</span>
            </td>
            <td class="imei-serial-cell">
                <div class="imei-serial-container">
                    <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="IMEI/Serial" readonly value="${itemData.expiry_imei_serial || ''}" style="${showImeiSerial ? '' : 'display:none;'}">
                </div>
            </td>
            <td>
                <span class="sale-quantity-display">${itemData.sale_quantity}</span>
            </td>
            <td>
                <input type="text" class="form-control number-input return-quantity" name="return_quantities[]" max="${itemData.available_return_quantity}" value="${defaultReturnQty}">
                <small class="text-muted">Max: ${itemData.available_return_quantity}</small>
            </td>
            <td>
                <span class="unit-price-sale">${saleUnitStr}</span>
                <input type="hidden" class="unit-price-sale-hidden" name="unit_prices_sale[]" value="${parseNumeric(unitPriceSale)}">
            </td>
            <td>
                <input type="text" class="form-control number-input unit-price-return" name="unit_prices_return[]" value="${returnUnitStr}">
            </td>
            <td>
                <span class="total-display">0.00</span>
                <input type="hidden" class="total-hidden" name="total[]" value="0">
            </td>
            <td>
                <button type="button" class="btn text-danger remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
        `;
        
        newRow.html(rowHtml);
        $('#saleReturnItems').append(newRow);
        
        // Calculate total for this row
        calculateRowTotal(newRow);
        
        // Update summary
        updateSummary();
    }

    // Row edits: delegated so server-rendered rows (edit form) recalculate grand total correctly
    $(document).on('input', '#saleReturnItems .return-quantity, #saleReturnItems .unit-price-return', function () {
       const row = $(this).closest('tr');
        calculateRowTotal(row);
        validateReturnQuantity(row);
        updateSummary();
    });

    $(document).on('click', '#saleReturnItems .remove-row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        updateRowNumbers();
        updateSummary();
    });

    // Function to calculate total for a row
    function calculateRowTotal(row) {
        const returnQty = parseNumeric(row.find('.return-quantity').val());
        const returnPrice = parseNumeric(row.find('.unit-price-return').val());
        const total = returnQty * returnPrice;
        const prec = (company_session_data && company_session_data.precision != null) ? parseInt(company_session_data.precision, 10) : 2;
        
        row.find('.total-display').text(total.toFixed(prec));
        row.find('.total-hidden').val(total.toFixed(prec));
    }

    // Function to validate return quantity
    function validateReturnQuantity(row) {
        const returnQty = parseNumeric(row.find('.return-quantity').val());
        const maxQty = parseNumeric(row.find('.return-quantity').attr('max'));
        const saleQty = parseNumeric(row.find('.sale-quantity').val()) || parseNumeric(row.find('.sale-quantity-display').text());
        
        if (returnQty > maxQty) {
            row.find('.return-quantity').addClass('is-invalid');
            showErrorNotification('Return quantity cannot exceed available quantity (' + maxQty + ')');
            return false;
        }
        
        if (returnQty > saleQty) {
            row.find('.return-quantity').addClass('is-invalid');
            showErrorNotification('Return quantity cannot exceed sale quantity (' + saleQty + ')');
            return false;
        }
        
        row.find('.return-quantity').removeClass('is-invalid');
        return true;
    }

    // Function to update row numbers
    function updateRowNumbers() {
        $('#saleReturnItems tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
        rowCounter = $('#saleReturnItems tr').length;
    }

    function amountPrecision() {
        return (company_session_data && company_session_data.precision != null)
            ? parseInt(company_session_data.precision, 10)
            : 2;
    }

    /** Show or hide paid vs grand total error (inline + notification-style message constant). */
    function updatePaidVersusGrandTotalFeedback(grandTotal) {
        const paidAmount = parseNumeric($('#paid').val());
        const $paid = $('#paid');
        const $hint = $('#sale_return_paid_exceeds_error');
        const exceeds = paidAmount > grandTotal + 0.001;
        if (exceeds) {
            $paid.addClass('is-invalid');
            $hint.removeClass('d-none');
        } else {
            $paid.removeClass('is-invalid');
            $hint.addClass('d-none');
        }
    }

    /** Keep user-entered paid; due = grand total − paid (not below zero on display). */
    function recalcDueAgainstGrand(grandTotal) {
        const prec = amountPrecision();
        const paidAmount = parseNumeric($('#paid').val());
        const dueAmount = grandTotal - paidAmount;
        const displayDue = Number.isFinite(dueAmount) && dueAmount > 0 ? dueAmount : 0;
        $('#due').val(displayDue.toFixed(prec));
        updatePaidVersusGrandTotalFeedback(grandTotal);
    }

    // Function to calculate and update summary
    function updateSummary() {
        let grandTotal = 0;
        let totalItems = 0;
        let totalQuantity = 0;

        $('#saleReturnItems tr').each(function() {
            const total = parseNumeric($(this).find('.total-hidden').val());
            const qty = parseNumeric($(this).find('.return-quantity').val());
            grandTotal += total;
            totalItems++;
            totalQuantity += qty;
        });

        const prec = amountPrecision();
        $('#grandTotal').val(grandTotal.toFixed(prec));
        if (totalItems === 0) {
            $('#paid').val('');
            $('#due').val(grandTotal.toFixed(prec));
            updatePaidVersusGrandTotalFeedback(grandTotal);
        } else {
            recalcDueAgainstGrand(grandTotal);
        }

        $('#totalItemCount').text(`Total Item ${totalItems} (${totalQuantity})`);
    }

    $(document).on('input blur', '#paid', function () {
        recalcDueAgainstGrand(parseNumeric($('#grandTotal').val()));
    });

    // Form submission (using event delegation to support dynamically loaded forms)
    $(document).on('submit', '#saleReturnForm', function(e) {
        e.preventDefault();
        
        // Check if this is POS context
        const isPosContext = $(this).data('is-pos') === true || $(this).data('is-pos') === 'true';
        
        // Clear previous errors
        clearValidationErrors();
        
        // Validate form
        if (!validateForm()) {
            return false;
        }
        
        // Show loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="ti tabler-loader-2 spin"></i> Processing...');
        
        // Determine form method and URL
        const formMethod = $(this).find('input[name="_method"]').val() || 'POST';
        const formAction = $(this).attr('action');
        
        // Submit form
        $.ajax({
            type: formMethod === 'PUT' ? 'POST' : 'POST',
            url: formAction,
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || (formMethod === 'PUT' ? 'Sale return updated successfully' : 'Sale return created successfully'));
                    
                    if (isPosContext) {
                        // POS context: reload form, switch to list tab, and reload list
                        setTimeout(function() {
                            // Reset form
                            $('#saleReturnForm')[0].reset();
                            $('#saleReturnItems').empty();
                            updateSummary();
                            
                            // Clear select2 values
                            $('#customer_id, #sale_id, #sale_item_id').val(null).trigger('change');
                            
                            // Helper function to reload the list
                            function reloadSaleReturnsList() {
                                // Reload list if DataTable exists (try both global and local variable)
                                if (typeof window.saleReturnsDataTable !== 'undefined' && window.saleReturnsDataTable) {
                                    try {
                                        window.saleReturnsDataTable.ajax.reload(null, false); // false = don't reset paging
                                    } catch(e) {
                                        console.error('Error reloading sale returns table:', e);
                                    }
                                } else if (typeof saleReturnsDataTable !== 'undefined' && saleReturnsDataTable) {
                                    try {
                                        saleReturnsDataTable.ajax.reload(null, false);
                                    } catch(e) {
                                        console.error('Error reloading sale returns table:', e);
                                    }
                                }
                                
                                // Also trigger custom event as fallback
                                $(document).trigger('saleReturnCreated');
                            }
                            
                            // Switch to list tab
                            const listTab = $('#pos_sale_returns_list_tab');
                            if (listTab.length) {
                                // Listen for tab shown event on the tab button (Bootstrap 5)
                                listTab.one('shown.bs.tab', function() {
                                    setTimeout(reloadSaleReturnsList, 200);
                                });
                                
                                // Trigger tab switch
                                listTab.tab('show');
                                
                                // Also reload after a delay to ensure it happens even if event doesn't fire
                                setTimeout(reloadSaleReturnsList, 600);
                            } else {
                                // If tab switching fails, just reload
                                reloadSaleReturnsList();
                            }
                            
                            submitBtn.prop('disabled', false).html(originalText);
                        }, 1500);
                    } else {
                        // Backend context: redirect to index
                        setTimeout(function() {
                            window.location.href = base_url + route('sale-return.index', {}, false, Ziggy);
                        }, 1500);
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#saleReturnForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Function to validate form
    function validateForm() {
        let isValid = true;
        
        // Validate customer
        if (!$('#customer_id').val()) {
            $('#customer_id').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate sale invoice
        if (!$('#sale_id').val()) {
            $('#sale_id').addClass('is-invalid');
            isValid = false;
        }
        
        // Validate items
        if ($('#saleReturnItems tr').length === 0) {
            showErrorNotification('Please add at least one item to return');
            isValid = false;
        }
        
        const grandTotal = parseNumeric($('#grandTotal').val());
        const paid = parseNumeric($('#paid').val());

        // Payment method only when capturing a refund payment now
        if (paid > 0.0001 && !$('#payment_method_id').val()) {
            $('#payment_method_id').addClass('is-invalid');
            $('#payment_method_id').next('.select2-container').addClass('is-invalid');
            showErrorNotification('Payment method is required when paid amount is greater than zero');
            isValid = false;
        }

        if (paid > grandTotal + 0.001) {
            $('#paid').addClass('is-invalid');
            $('#sale_return_paid_exceeds_error').removeClass('d-none');
            showErrorNotification(PAID_EXCEEDS_GRAND_TOTAL_MSG);
            isValid = false;
        }
        
        // Validate return quantities
        let hasInvalidQty = false;
        $('#saleReturnItems tr').each(function() {
            if (!validateReturnQuantity($(this))) {
                hasInvalidQty = true;
            }
        });
        
        if (hasInvalidQty) {
            isValid = false;
        }
        
        return isValid;
    }

    // Initialize date picker (for both static and dynamically loaded forms)
    function initializeDatePickers(container) {
        const target = container || document;
        $(target).find('.datePicker').each(function() {
            if (!$(this).hasClass('flatpickr-input') && !$(this).data('flatpickr')) {
                $(this).flatpickr({
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                    static: true,
                    allowInput: true
                });
            }
        });
    }
    
    // Initialize on page load
    initializeDatePickers();

    // Recalculate server-rendered rows (edit sale return) and sync grand total / paid
    $('#saleReturnItems tr').each(function () {
        calculateRowTotal($(this));
    });
    updateSummary();
    
    // Expose function globally for POS context
    window.initializeSaleReturnDatePickers = function(container) {
        initializeDatePickers(container);
    };
});
