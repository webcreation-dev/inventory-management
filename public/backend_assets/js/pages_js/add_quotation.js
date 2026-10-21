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
  
    let variation_child_items_url = $('#variation_child_items_url').val();
    // Function to check if an item already exists in table
    function itemExists(itemId) {
        let exists = false;
        $('#quotationItems tr').each(function() {
            if ($(this).find('.item-id').val() === itemId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
    
    // Function to check if a variation product's child items already exist in the table
    function variationExists(parentId) {
        let exists = false;
        $('#quotationItems tr').each(function() {
            if ($(this).find('.parent-id').val() === parentId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
    
    // Function to calculate subtotal of all items
    function calculateSubtotal() {
        let subtotal = 0;
        $('#quotationItems tr').each(function() {
            const total = parseFloat($(this).find('.total').val()) || 0;
            subtotal += total;
        });
        return subtotal;
    }
    
    // Function to update total item count
    function updateTotalItemCount() {
        const count = $('#quotationItems tr').length;
        $('#totalItemCount').text(count);
    }
    
    // Function to calculate and update summary
    function updateSummary() {
        const subtotal = calculateSubtotal();
        const discountValue = $('#discount').val();
        let discountAmount = 0;
        
        // Calculate discount
        if (discountValue) {
            if (discountValue.includes('%')) {
                const discountPercent = parseFloat(discountValue.replace('%', '')) || 0;
                discountAmount = subtotal * (discountPercent / 100);
            } else {
                discountAmount = parseFloat(discountValue) || 0;
            }
        }
        
        // Calculate grand total
        const grandTotal = subtotal - discountAmount;
        
        // Update display
        $('#grandTotal').val(grandTotal.toFixed(2));
        updateTotalItemCount();
    }
    
    // Function to add a new row to the quotation items table
    function addNewRow(itemData = null) {
        const rowCount = $('#quotationItems tr').length;
        const newRow = $('<tr></tr>');
        
        // Create row HTML
        let rowHtml = `
            <td style="vertical-align: top;">${rowCount + 1}</td>
            <td style="vertical-align: top;">
                <input type="hidden" class="item-id" name="items[]" value="${itemData ? itemData.id : ''}">
                <input type="hidden" name="custom_item_names[]" value="">
                <input type="hidden" class="parent-id" name="parent_ids[]" value="${itemData && itemData.parentId ? itemData.parentId : ''}">
                <span class="item-name">${itemData ? itemData.name : ''}</span>
            </td>
            <td style="vertical-align: top;">
                <div class="input-group">
                    <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="1">
                    <button type="button" class="btn btn-outline-secondary" type="button">
                        ${itemData ? itemData.saleUnitName : ''}
                    </button>
                </div>
            </td>
            <td style="vertical-align: top;">
                <input type="text" class="form-control number-input unit-price" name="unit_price[]">
            </td>
            <td style="vertical-align: top;">
                <input type="text" class="form-control number-input total" name="total[]" readonly>
            </td>
            <td style="vertical-align: top;">
                <textarea class="form-control description" name="description[]" rows="2" placeholder="Enter description"></textarea>
            </td>
            <td style="vertical-align: top;">
                <button type="button" class="btn text-danger remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
        `;
        
        newRow.html(rowHtml);
        $('#quotationItems').append(newRow);
        
        // If item data is provided, populate the row
        if (itemData) {
            newRow.find('.unit-price').val(itemData.salePrice);
            calculateTotal(newRow);
        }
        
        // Initialize row events
        initializeRowEvents(newRow);
    }
    
    // Function to initialize events for a row
    function initializeRowEvents(row) {
        // Quantity and unit price change events
        row.find('.quantity, .unit-price').on('input', function() {
            calculateTotal(row);
        });
        
        // Remove row button click event
        row.find('.remove-row').on('click', function() {
            row.remove();
            updateRowNumbers();
            updateSummary();
        });
    }

    // ===============================
    // FIX FOR EDIT PAGE CALCULATION
    // ===============================
    $('#quotationItems tr').each(function () {
        const row = $(this);

        // Attach events to existing rows
        initializeRowEvents(row);

        // Recalculate row total (important if qty/price changed)
        calculateTotal(row);
    });

    // Recalculate summary after all rows are initialized
    updateRowNumbers();
    updateSummary();
    
    // Function to calculate total for a row
    function calculateTotal(row) {
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
        const total = quantity * unitPrice;
        row.find('.total').val(total.toFixed(2));
        
        // Update summary when row total changes
        updateSummary();
    }
    
    // Function to update row numbers
    function updateRowNumbers() {
        $('#quotationItems tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    // Event listeners for summary
    $('#discount').on('input', function() {
        updateSummary();
    });
    
    // Initialize summary on page load
    updateSummary();

    function updateExtraQuotationModalTotal() {
        var qty = parseFloat($('#extra_quotation_item_qty').val()) || 0;
        var price = parseFloat($('#extra_quotation_item_unit_price').val()) || 0;
        $('#extra_quotation_item_total').val((qty * price).toFixed(2));
    }

    $('#extra_quotation_item_qty, #extra_quotation_item_unit_price').on('input', updateExtraQuotationModalTotal);

    function openExtraQuotationItemModal() {
        $('#extra_quotation_item_name').val('');
        $('#extra_quotation_item_qty').val('1');
        $('#extra_quotation_item_unit_price').val('');
        $('#extra_quotation_item_description').val('');
        updateExtraQuotationModalTotal();
        var el = document.getElementById('modal_extra_quotation_item');
        if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(el);
            if (!modal) {
                modal = new bootstrap.Modal(el);
            }
            modal.show();
        }
    }

    /**
     * Row for a quotation-only line (no inventory item id).
     */
    function addExtraQuotationRow(name, quantity, unitPrice, description) {
        var safeName = String(name || '').trim();
        var qtyInt = parseInt(String(quantity), 10);
        if (!Number.isFinite(qtyInt) || qtyInt < 1) {
            qtyInt = 1;
        }
        var priceNum = parseFloat(unitPrice);
        if (!Number.isFinite(priceNum)) {
            priceNum = 0;
        }
        var descStr = description !== undefined && description !== null ? String(description) : '';
        var quotationOnlyLabel =
            $('#modal_extra_quotation_item').attr('data-i18n-quotation-only') || 'Quotation only';

        var newRow = $('<tr></tr>');
        newRow.html(
            '<td style="vertical-align: top;"></td>' +
                '<td style="vertical-align: top;">' +
                '<input type="hidden" class="item-id" name="items[]" value="">' +
                '<input type="hidden" name="custom_item_names[]" value="">' +
                '<input type="hidden" class="parent-id" name="parent_ids[]" value="">' +
                '<span class="item-name"></span> ' +
                '</td>' +
                '<td style="vertical-align: top;">' +
                '<div class="input-group">' +
                '<input type="text" class="form-control number-input quantity" name="quantity[]" min="1">' +
                '<button type="button" class="btn btn-outline-secondary" type="button">—</button>' +
                '</div>' +
                '</td>' +
                '<td style="vertical-align: top;"><input type="text" class="form-control number-input unit-price" name="unit_price[]"></td>' +
                '<td style="vertical-align: top;"><input type="text" class="form-control number-input total" name="total[]" readonly></td>' +
                '<td style="vertical-align: top;"><textarea class="form-control description" name="description[]" rows="2" placeholder="Enter description"></textarea></td>' +
                '<td style="vertical-align: top;"><button type="button" class="btn text-danger remove-row">' +
                '<i class="icon-base ti tabler-trash"></i></button></td>'
        );
        newRow.find('input[name="custom_item_names[]"]').val(safeName);
        newRow.find('.item-name').text(safeName);
        newRow.find('.quotation-only-badge').text(quotationOnlyLabel);
        newRow.find('.quantity').val(qtyInt);
        newRow.find('.unit-price').val(priceNum);
        newRow.find('.description').val(descStr);

        $('#quotationItems').append(newRow);
        initializeRowEvents(newRow);
        calculateTotal(newRow);
        updateRowNumbers();
        updateSummary();
    }

    $('.add-item-row').on('click', function () {
        openExtraQuotationItemModal();
    });

    $('#btn_confirm_extra_quotation_item').on('click', function () {
        var name = String($('#extra_quotation_item_name').val() || '').trim();
        if (!name) {
            showErrorNotification(language_key['Please enter a name'] || 'Please enter a name');
            return;
        }
        var quantity = parseInt(String($('#extra_quotation_item_qty').val()), 10);
        var unitPrice = parseFloat($('#extra_quotation_item_unit_price').val()) || 0;
        if (!Number.isFinite(quantity) || quantity < 1) {
            showErrorNotification(language_key['Please enter a valid quantity'] || 'Please enter a valid quantity');
            return;
        }
        if (unitPrice < 0) {
            showErrorNotification(language_key['Please enter a valid unit price'] || 'Please enter a valid unit price');
            return;
        }
        var description = $('#extra_quotation_item_description').val() || '';
        addExtraQuotationRow(name, quantity, unitPrice, description);
        var el = document.getElementById('modal_extra_quotation_item');
        if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modalInst = bootstrap.Modal.getInstance(el);
            if (modalInst) {
                modalInst.hide();
            }
        }
    });
  
    // Quick item select change event
    $('#quickItemSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const itemId = $(this).val();
        const itemType = selectedOption.data('item-type');
        
        if (!itemId) return;
        
        const itemData = {
            id: itemId,
            name: selectedOption.data('item-name'),
            salePrice: parseFloat(selectedOption.data('sale-price')) || 0,
            type: itemType,
            saleUnitName: selectedOption.data('sale-unit-name'),
            purchaseUnitName: selectedOption.data('purchase-unit-name')
        };
        
        // Check for duplicate items for General_Product and Installment_Product
        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
                if (itemExists(itemId)) {
                    showErrorNotification('This item already exists in the quotation list.');
                    $(this).val(''); // Reset the select
                    return;
                }
                // Add item directly to the table
                addNewRow(itemData);
        } else if (itemType === 'Variation_Product') {
            // Check if this variation product's child items already exist
            if (variationExists(itemId)) {
                showErrorNotification('This variation product already exists in the quotation list.');
                $(this).val(''); // Reset the select
                return;
            }

            // Parent name for display: "ParentName - ChildName (code)"
            const parentNameOnly = selectedOption.data('item-name-only') || selectedOption.data('item-name') || '';
            
            // Fetch child items via AJAX
            $.ajax({
                url: variation_child_items_url || (base_url + '/quotation/variation-child-items'),
                type: 'GET',
                data: { parent_id: itemId },
                success: function(response) {
                    if (response.length > 0) {
                        // Add each child item with parent name, sale unit, and sale unit price
                        response.forEach(function(childItem) {
                            const saleUnit = childItem.sale_unit || childItem.saleUnit;
                            const saleUnitName = (saleUnit && saleUnit.unit_name) ? saleUnit.unit_name : '';
                            const childData = {
                                id: childItem.id,
                                name: parentNameOnly ? (parentNameOnly + ' - ' + childItem.name + ' (' + childItem.code + ')') : (childItem.name + ' (' + childItem.code + ')'),
                                salePrice: parseFloat(childItem.sale_price) || 0,
                                type: '0',
                                parentId: itemId,
                                saleUnitName: saleUnitName
                            };
                            addNewRow(childData);
                        });
                    } else {
                        showErrorNotification('No child items found for this variation product.');
                    }
                },
                error: function() {
                    showErrorNotification('Error fetching child items. Please try again.');
                }
            });
        } else {
            // For IMEI_Product or Serial_Product, add directly (simplified for quotation)
            if (itemExists(itemId)) {
                showErrorNotification('This item already exists in the quotation list.');
                $(this).val(''); // Reset the select
                return;
            }
            addNewRow(itemData);
        }
        
        // Reset the select
        $(this).val('');
    });
  
    // Save and Email / Save and Print: set action then submit (validation runs on submit)
    $('.btn-save-email, .btn-save-print').on('click', function() {
        var action = $(this).data('action');
        $('#submit_action').val(action || '');
        $('#quotationForm').submit();
    });

    // Form submission validation
    $('#quotationForm').on('submit', function(e) {
        let isValid = true;
        const errors = [];
        
        // Check if there are any items in the table
        if ($('#quotationItems tr').length === 0) {
            errors.push('Please add at least one item to the quotation');
            isValid = false;
        } else {
            // Validate quotation items
            $('#quotationItems tr').each(function(index) {
                const itemId = $(this).find('.item-id').val();
                const customNameInput = $(this).find('input[name="custom_item_names[]"]');
                const customName = customNameInput.length ? String(customNameInput.val() || '').trim() : '';

                if (!itemId && !customName) {
                    errors.push(`Row ${index + 1}: Please select an item or use an extra quotation item with a name`);
                    isValid = false;
                    return;
                }
                
                const quantity = parseFloat($(this).find('.quantity').val()) || 0;
                const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
                
                if (quantity <= 0) {
                    errors.push(`Row ${index + 1}: Please enter a valid quantity`);
                    isValid = false;
                }
                
                if (unitPrice <= 0) {
                    errors.push(`Row ${index + 1}: Please enter a valid unit price`);
                    isValid = false;
                }
            });
        }
        
        // Validate customer
        if (!$('#customer_id').val()) {
            errors.push('Please select a customer');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showErrorNotification(errors.join('\n'));
        }
    });
  

  
    // Load existing quotation data if editing
    if (typeof quotationDetails !== 'undefined' && quotationDetails && quotationDetails.length > 0) {
        quotationDetails.forEach(function(detail) {
            const existingItemData = {
                id: detail.item_id,
                name: detail.item_name,
                salePrice: detail.unit_price,
                type: detail.item_type || 'General_Product'
            };
            
            const existingRow = $('<tr></tr>');
            const rowCount = $('#quotationItems tr').length;
            const rowHtml = `
                <td style="vertical-align: top;">${rowCount + 1}</td>
                <td style="vertical-align: top;">
                    <input type="hidden" class="item-id" name="items[]" value="${existingItemData.id}">
                    <input type="hidden" name="custom_item_names[]" value="">
                    <input type="hidden" class="parent-id" name="parent_ids[]" value="">
                    <span class="item-name">${existingItemData.name}</span>
                </td>
                <td style="vertical-align: top;">
                    <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="${detail.quantity}">
                </td>
                <td style="vertical-align: top;">
                    <input type="text" class="form-control number-input unit-price" name="unit_price[]" value="${detail.unit_price}">
                </td>
                <td style="vertical-align: top;">
                    <input type="text" class="form-control number-input total" name="total[]" readonly value="${detail.total}">
                </td>
                <td style="vertical-align: top;">
                    <textarea class="form-control description" name="description[]" rows="2" placeholder="Enter description">${detail.description || ''}</textarea>
                </td>
                <td style="vertical-align: top;">
                    <button type="button" class="btn btn-danger btn-sm remove-row">
                        <i class="icon-base ti tabler-trash"></i>
                    </button>
                </td>
            `;
            existingRow.html(rowHtml);
            $('#quotationItems').append(existingRow);
            initializeRowEvents(existingRow);
        });
        updateRowNumbers();
        updateSummary();
    }
});

