/**
 * POS Payment Manager
 * Handles payment modal operations for POS system
 */

function posExtractSaleApiMessage(result) {
    if (!result || typeof result !== 'object') return '';
    if (result.message) return String(result.message);
    if (result.errors && typeof result.errors === 'object') {
        try {
            return Object.values(result.errors).flat().filter(Boolean).join(' ');
        } catch (e) {
            return '';
        }
    }
    return '';
}

class POSPaymentManager {
    constructor() {
        this.totalPayable = 0;
        this.selectedPayments = []; // Array of {payment_id, payment_name, account_type, amount}
        this.currentPaymentMethod = null; // Currently selected payment method for adding
        this.stripe = null;
        this.stripeElements = null;
        this.stripePaymentElement = null;
        this.currentGatewayPayment = null; // Current gateway payment being processed
        this.lastCustomerLoyaltyInfo = null;
        this.init();
        this.company_session_data = this.getCompanySessionData() || {};
        this.activeMultiCurrency = 'No';
        this.activeCurrencyRate = 1;
        this.activeCurrencyType = null;
        this._posMultiCurrenciesList = [];
        this.loadPosMultipleCurrenciesFromDom();
    }

    getCompanySessionData() {
        try {
            let company_data =  JSON.parse($('#company_data').val()) || {};
            company_data.default_employee_id = $('.default_employee_id').val() || null;
            return company_data;
        } catch (e) {
            console.error('Error parsing company info:', e);
            return {};
        }
    }

    loadPosMultipleCurrenciesFromDom() {
        this._posMultiCurrenciesList = [];
        const $src = $('#pos-payment-multiple-currencies-json');
        if (!$src.length) {
            return;
        }
        const raw = String($src.text() || '').trim();
        if (!raw) {
            return;
        }
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                this._posMultiCurrenciesList = parsed.filter(c => c && c.currency && parseFloat(c.conversion_rate) > 0);
            }
        } catch (e) {
            console.warn('POS multiple currencies: invalid JSON', e);
            this._posMultiCurrenciesList = [];
        }
    }

    resetPaymentCurrencyToBase() {
        this.activeMultiCurrency = 'No';
        this.activeCurrencyRate = 1;
        this.activeCurrencyType = null;
        const $sel = $('#pos-payment-currency-select');
        if ($sel.length) {
            $sel.val('__base__');
        }
        this.refreshPaymentCurrencyUi();
    }

    /**
     * @param {boolean} showCurrencyRow - When true, reveal the currency row (e.g. user clicked "Change Currency").
     */
    populatePaymentCurrencySelect(showCurrencyRow = false) {
        this.loadPosMultipleCurrenciesFromDom();
        const $row = $('#pos-payment-currency-row');
        const $sel = $('#pos-payment-currency-select');
        if (!$row.length || !$sel.length) {
            return;
        }
        if (!this._posMultiCurrenciesList.length) {
            $row.hide();
            $sel.empty();
            this.resetPaymentCurrencyToBase();
            return;
        }
        const baseLabel = ($('#modal_pos_payment').attr('data-base-currency') || '').trim() || 'Base';
        $sel.empty();
        $sel.append($('<option>', { value: '__base__', text: baseLabel + ' (Default)' }));
        this._posMultiCurrenciesList.forEach((c) => {
            const rate = parseFloat(c.conversion_rate);
            if (!Number.isFinite(rate) || rate <= 0) {
                return;
            }
            const label = (c.currency || '') + ' (× ' + this.formatNumber(rate) + ')';
            $sel.append($('<option>', {
                value: String(c.id),
                text: label,
            }).attr('data-currency', c.currency).attr('data-rate', String(rate)));
        });
        $sel.val('__base__');
        this.resetPaymentCurrencyToBase();
        if (showCurrencyRow) {
            $row.show();
        } else {
            $row.hide();
        }
    }

    refreshPaymentCurrencyUi() {
        const $hint = $('#pos-payment-currency-hint');
        const baseLabel = ($('#modal_pos_payment').attr('data-base-currency') || '').trim() || '';
        if (this.activeMultiCurrency === 'Yes' && this.activeCurrencyType) {
            const rateTxt = this.activeCurrencyRate > 0 ? this.formatNumber(this.activeCurrencyRate) : '';
            $hint.text(rateTxt ? (`1 ${baseLabel || 'base'} = ${rateTxt} ${this.activeCurrencyType}`) : '');
            const cm = this.currentPaymentMethod;
            if (cm && cm.account_type === 'Cash') {
                $('#denomination-section').hide();
                $('.payment-details-section').css('grid-template-columns', '1fr');
            }
        } else {
            $hint.text('');
            const cm = this.currentPaymentMethod;
            if (cm && cm.account_type === 'Cash') {
                $('#denomination-section').show();
                $('.payment-details-section').css('grid-template-columns', '3fr 2fr');
            }
        }
    }

    paymentInputFromBase(baseAmt) {
        const b = this.parsePosAmount(baseAmt);
        if (this.activeMultiCurrency === 'Yes' && this.activeCurrencyRate > 0) {
            return this.roundMoney(b * this.activeCurrencyRate);
        }
        return b;
    }

    baseFromPaymentInput(inputAmt) {
        const x = this.parsePosAmount(inputAmt);
        if (this.activeMultiCurrency === 'Yes' && this.activeCurrencyRate > 0) {
            return this.roundMoney(x / this.activeCurrencyRate);
        }
        return x;
    }

    /**
     * Parse currency/amount text safely (strips commas, avoids NaN).
     */
    parsePosAmount(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }
        const cleaned = String(value).replace(/,/g, '').trim();
        if (cleaned === '' || cleaned === '.') {
            return 0;
        }
        const n = parseFloat(cleaned);
        return Number.isFinite(n) ? n : 0;
    }

    /**
     * Normalize monetary values for arithmetic and API payloads.
     */
    roundMoney(value) {
        const n = Number(value) || 0;
        return parseFloat(n.toFixed(3));
    }

    paymentLineUsesMultiCurrency(accountType, gatewayData) {
        return !gatewayData && accountType !== 'Loyalty Point' && accountType !== 'Gateway' && this.activeMultiCurrency === 'Yes' && this.activeCurrencyRate > 0;
    }

    /**
     * Initialize payment manager
     */
    init() {
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const self = this;

        // Default payment method button click handler
        function defaultPaymentMethodButtonClick() {
            $('.payment-method-btn').each(function() {
                if ($(this).data('default') == 'Yes') {
                    const paymentId = $(this).data('payment-id');
                    const paymentName = $(this).data('payment-name');
                    const accountType = $(this).data('account-type');
                    const gatewayName = $(this).data('gateway-name') || null;
                    
                    // Skip if it's the "Change Currency" button (no payment-id)
                    if (!paymentId) {
                        return;
                    }

                    if (accountType === 'Loyalty Point' && self.isWalkInCustomer()) {
                        return;
                    }
                    
                    // Set current payment method
                    self.currentPaymentMethod = {
                        id: paymentId,
                        name: paymentName,
                        account_type: accountType,
                        gateway_name: gatewayName
                    };
                    if (accountType === 'Loyalty Point' || gatewayName) {
                        self.resetPaymentCurrencyToBase();
                    }
                    
                    // Update button styles
                    $('.payment-method-btn').removeClass('btn-primary');
                    $('.payment-method-btn').addClass('btn-outline-primary');
                    $(this).addClass('btn-primary');
                    $(this).removeClass('btn-outline-primary');
                    
                    // Show denomination section if Cash and adjust grid layout
                    const $paymentDetailsSection = $('.payment-details-section');
                    if (accountType === 'Cash') {
                        $('#denomination-section').show();
                        self.resetDenomination();
                        $paymentDetailsSection.css('grid-template-columns', '3fr 2fr');
                        $('#gateway-payment-wrapper').hide();
                    } else if (gatewayName) {
                        // Gateway payment
                        $('#denomination-section').hide();
                        $paymentDetailsSection.css('grid-template-columns', '1fr');
                        $('#gateway-payment-wrapper').show();
                        const gatewayTitle = self.getGatewayTitle(gatewayName);
                        $('#gateway-payment-title').text(gatewayTitle);
                        // Initialize gateway payment
                        self.initializeGatewayPayment(paymentId, gatewayName);
                    } else {
                        $('#denomination-section').hide();
                        $('#gateway-payment-wrapper').hide();
                        $paymentDetailsSection.css('grid-template-columns', '1fr');
                    }
                    return false;
                } 
            });
            self.updatePaymentAmountFieldMode();
            self.refreshPaymentCurrencyUi();
        }
        
        $(document).on('click', '#pos-payment-btn', function(e) {
            // Will be called after modal is shown
        });

        // Payment method button click
        $(document).on('click', '.payment-method-btn', function(e) {
            e.preventDefault();
            const paymentId = $(this).data('payment-id');
            const paymentName = $(this).data('payment-name');
            const accountType = $(this).data('account-type');
            const gatewayName = $(this).data('gateway-name') || null;

            console.log(gatewayName);
            
            // Skip if it's the "Change Currency" button (no payment-id)
            if (!paymentId) {
                return;
            }

            if (accountType === 'Loyalty Point' && self.isWalkInCustomer()) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Loyalty points cannot be used for Walk-in Customer.');
                } else {
                    alert('Loyalty points cannot be used for Walk-in Customer.');
                }
                return;
            }
            
            // Check if payment method already exists
            if (self.isPaymentMethodAdded(paymentId)) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`${paymentName} is already added. Please remove it first to add again.`);
                } else {
                    alert(`${paymentName} is already added. Please remove it first to add again.`);
                }
                return;
            }

            // Remove active class from all payment method buttons
            $('.payment-method-btn').removeClass('btn-primary');
            $('.payment-method-btn').removeClass('btn-outline-primary');
            // Add active class to clicked button
            $(this).addClass('btn-primary');
            $(this).removeClass('btn-outline-primary');

            // Set current payment method
            self.currentPaymentMethod = {
                id: paymentId,
                name: paymentName,
                account_type: accountType,
                gateway_name: gatewayName
            };
            if (accountType === 'Loyalty Point' || gatewayName) {
                self.resetPaymentCurrencyToBase();
            }

            // Show denomination section if Cash and adjust grid layout
            const $paymentDetailsSection = $('.payment-details-section');
            if (accountType === 'Cash') {
                $('#denomination-section').show();
                self.resetDenomination();
                // Change grid to single column when Cash is selected
                $paymentDetailsSection.css('grid-template-columns', '3fr 2fr');
                $('#gateway-payment-wrapper').hide();
            } else if (gatewayName) {
                // Gateway payment (Stripe, PayPal, etc.)
                $('#denomination-section').hide();
                $paymentDetailsSection.css('grid-template-columns', '1fr');
                // Show gateway payment element
                $('#gateway-payment-wrapper').show();
                // Update gateway payment title
                const gatewayTitle = self.getGatewayTitle(gatewayName);
                $('#gateway-payment-title').text(gatewayTitle);
                // Initialize gateway payment
                self.initializeGatewayPayment(paymentId, gatewayName);
            } else {
                // Regular payment method (no gateway)
                $('#denomination-section').hide();
                $('#gateway-payment-wrapper').hide();
                // Restore original grid layout for non-Cash payments
                $paymentDetailsSection.css('grid-template-columns', '1fr');
            }

            self.updatePaymentAmountFieldMode();
            self.refreshPaymentCurrencyUi();

            // Focus on payment amount input (if not a gateway payment)
            if (!gatewayName) {
                $('#payment-amount-input').val('').focus();
            }
        });

        // Payment amount input - only update denomination total for Cash, don't auto-add
        $(document).on('input', '#payment-amount-input', function(e) {
            // Only update denomination total if Cash is selected
            if (self.currentPaymentMethod && self.currentPaymentMethod.account_type === 'Cash') {
                const amount = parseFloat($(this).val()) || 0;
                // Don't auto-add, just allow user to type
            }
        });

        // Payment amount input Enter key handler
        $(document).on('keypress', '#payment-amount-input', function(e) {
            if (e.which === 13 || e.keyCode === 13) { // Enter key
                e.preventDefault();
                self.handleAddPayment();
            }
        });

        // Cannot allocate more than remaining payable on this line — except for Cash,
        // where the customer may hand over more than the remaining (change is given back).
        $(document).on('blur', '#payment-amount-input', function() {
            const v = parseFloat(String($(this).val()).replace(/,/g, '')) || 0;
            const max = self.getRemainingPayable();
            const isCash = self.currentPaymentMethod && self.currentPaymentMethod.account_type === 'Cash';
            const isLoyalty = self.currentPaymentMethod && self.currentPaymentMethod.account_type === 'Loyalty Point';
            if (isLoyalty && !self.isWalkInCustomer()) {
                const info = self.lastCustomerLoyaltyInfo || {};
                const rate = parseFloat(info.loyalty_rate) || 0;
                const available = parseFloat(info.available_loyalty_points);
                if (!Number.isFinite(v) || v <= 0) {
                    return;
                }
                if (Number.isFinite(available) && v > available + 1e-6) {
                    self.showLoyaltyPointsCapToast(available);
                    $(this).val(self.formatPointsForDisplay(available));
                    return;
                }
                const maxPtsLine = self.getMaxLoyaltyPointsForLine();
                if (Number.isFinite(maxPtsLine) && v > maxPtsLine + 1e-6) {
                    $(this).val(self.formatPointsForDisplay(maxPtsLine));
                    return;
                }
                $(this).val(self.formatPointsForDisplay(v));
                return;
            }
            if (!isCash && max > 0) {
                const maxInput = self.paymentInputFromBase(max);
                if (v > maxInput + 0.0001) {
                    $(this).val(self.formatNumber(maxInput));
                }
            }
        });

        $(document).on('change', '#pos-payment-currency-select', function() {
            const v = $(this).val();
            if (v === '__base__') {
                self.resetPaymentCurrencyToBase();
            } else {
                const opt = $(this).find('option:selected');
                const cur = opt.attr('data-currency');
                const rate = parseFloat(opt.attr('data-rate'));
                if (cur && Number.isFinite(rate) && rate > 0) {
                    self.activeMultiCurrency = 'Yes';
                    self.activeCurrencyRate = rate;
                    self.activeCurrencyType = cur;
                } else {
                    self.resetPaymentCurrencyToBase();
                }
            }
            self.refreshPaymentCurrencyUi();
            self.updateRemainingAmount();
            const fill = self.getNextPaymentPrefillAmount();
            if (fill > 0 && self.currentPaymentMethod && !self.currentPaymentMethod.gateway_name) {
                const isLoyalty = self.currentPaymentMethod.account_type === 'Loyalty Point';
                $('#payment-amount-input').val(isLoyalty ? self.formatPointsForDisplay(fill) : self.formatNumber(fill));
            }
        });

        // Denomination input change
        $(document).on('input', '.denomination-input', function(e) {
            self.calculateDenominationTotal();
            const total = self.getDenominationTotal();
            // Auto-fill payment amount input with denomination total for Cash payments.
            // For Cash we allow the raw denomination total (may exceed remaining → change on Add).
            if (total > 0 && self.currentPaymentMethod && self.currentPaymentMethod.account_type === 'Cash') {
                $('#payment-amount-input').val(self.formatNumber(total));
            }
        });

        // Add payment button
        $(document).on('click', '#add-payment-btn', function(e) {
            e.preventDefault();
            self.handleAddPayment();
        });

        // Remove payment method
        $(document).on('click', '.remove-payment-btn', function(e) {
            e.preventDefault();
            const paymentId = parseInt($(this).data('payment-id'));
            self.removePaymentMethod(paymentId);
        });

        $(document).on('click', '.pos-change-currency-btn', function(e) {
            e.preventDefault();
            self.loadPosMultipleCurrenciesFromDom();
            if (!self._posMultiCurrenciesList.length) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('No alternate currencies are configured. Add them under Multiple Currency in settings.');
                } else {
                    alert('No alternate currencies are configured. Add them under Multiple Currency in settings.');
                }
                return;
            }
            self.populatePaymentCurrencySelect(true);
            $('#pos-payment-currency-select').trigger('focus');
        });

        // Payment modal shown event
        $('#modal_pos_payment').on('shown.bs.modal', async function() {
            self.resetPaymentModal();
            self.populatePaymentCurrencySelect(false);
            // Refresh company session data and set Email, SMS, WhatsApp checkboxes from session defaults
            
            self.company_session_data = self.getCompanySessionData() || {};

            $('#email').prop('checked', self.company_session_data.smtp_default_selected_in_pos === 'Yes');
            $('#sms').prop('checked', self.company_session_data.sms_default_selected_in_pos === 'Yes');
            $('#whatsapp').prop('checked', self.company_session_data.whatsapp_default_selected_in_pos === 'Yes');
            // Set default payment method after modal is shown
            defaultPaymentMethodButtonClick();
            self.toggleLoyaltyPaymentButtons();
            // Pre-fill payment amount with remaining payable for fast sale completion
            const totalText = $('#pos-payment-total').text();
            const total = parseFloat(String(totalText).replace(/,/g, '')) || 0;
            if (total > 0) {
                self.totalPayable = self.roundMoney(total);
            }
            self.updateRemainingAmount();
            await self.refreshPaymentModalCustomerSummary();
            self.updatePaymentAmountFieldMode();
            const fill = self.getNextPaymentPrefillAmount();
            if (fill > 0) {
                const isLoyalty = self.currentPaymentMethod && self.currentPaymentMethod.account_type === 'Loyalty Point';
                $('#payment-amount-input').val(isLoyalty ? self.formatPointsForDisplay(fill) : self.formatNumber(fill));
            }
        });

        $(document).on('change', '#customer-select', async function() {
            if ($('#modal_pos_payment').hasClass('show')) {
                if (self.isWalkInCustomer()) {
                    self.removeLoyaltyPaymentsFromSummary();
                }
                self.toggleLoyaltyPaymentButtons();
                await self.refreshPaymentModalCustomerSummary();
                self.updatePaymentAmountFieldMode();
                self.updateRemainingAmount();
            }
        });

        // Payment modal hidden event
        $('#modal_pos_payment').on('hidden.bs.modal', function() {
            self.resetPaymentModal();
        });

        // Submit payment
        $(document).on('click', '#pos-payment-submit-btn', function(e) {
            e.preventDefault();
            self.submitPayment(false);
        });

        // Due Sale button - create sale as due (not allowed for Walk-in Customer)
        $(document).on('click', '#pos-due-sale-btn', function(e) {
            e.preventDefault();
            const walkInCustomerId = $('#modal_pos_payment').data('walk-in-customer-id');
            const selectedCustomerId = $('#customer-select').val();
            if (walkInCustomerId && String(selectedCustomerId) === String(walkInCustomerId)) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Due sale is not allowed for Walk-in Customer. Please select a different customer.');
                } else {
                    alert('Due sale is not allowed for Walk-in Customer. Please select a different customer.');
                }
                return;
            }
            Swal.fire({
                title: 'Create Due Sale?',
                text: 'This sale will be recorded with full amount as due. No payment will be collected now.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create due sale',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    self.submitPayment(true);
                }
            });
        });

        // Confirm gateway payment
        $(document).on('click', '#gateway-confirm-payment-btn', function(e) {
            e.preventDefault();
            if (self.currentGatewayPayment && self.currentGatewayPayment.gatewayName) {
                self.confirmGatewayPayment(self.currentGatewayPayment.gatewayName);
            }
        });
    }

    /**
     * Handle add payment (called from button click or Enter key)
     */
    async handleAddPayment() {
        let amount = this.parsePosAmount($('#payment-amount-input').val());
        
        if (!this.currentPaymentMethod) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Please select a payment method first');
            } else {
                alert('Please select a payment method first');
            }
            return;
        }

        const maxRemaining = this.getRemainingPayable();
        if (maxRemaining <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Total payable is already covered. Remove a payment line to change amounts.');
            } else {
                alert('Total payable is already covered. Remove a payment line to change amounts.');
            }
            return;
        }

        // Handle gateway payments (Stripe, PayPal, etc.)
        if (this.currentPaymentMethod.gateway_name) {
            if (amount <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Please enter a valid payment amount');
                } else {
                    alert('Please enter a valid payment amount');
                }
                return;
            }
            amount = this.clampPaymentLineAmount(amount);
            if (amount <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`Payment amount cannot exceed remaining payable (${this.formatNumber(maxRemaining)}).`);
                } else {
                    alert(`Payment amount cannot exceed remaining payable (${this.formatNumber(maxRemaining)}).`);
                }
                return;
            }
            const rawIn = parseFloat(String($('#payment-amount-input').val()).replace(/,/g, '')) || 0;
            if (rawIn > amount + 0.0001) {
                $('#payment-amount-input').val(this.formatNumber(amount));
            }
            // Process gateway payment
            await this.processGatewayPayment(amount, this.currentPaymentMethod.gateway_name);
            return;
        }

        // Loyalty Point: payment input is POINTS redeemed; invoice line amount = points × loyalty_rate.
        if (this.currentPaymentMethod.account_type === 'Loyalty Point') {
            if (this.isWalkInCustomer()) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Loyalty points cannot be used for Walk-in Customer.');
                } else {
                    alert('Loyalty points cannot be used for Walk-in Customer.');
                }
                return;
            }

            await this.refreshPaymentModalCustomerSummary();
            const info = this.lastCustomerLoyaltyInfo || {};
            const rate = parseFloat(info.loyalty_rate) || 0;
            const minPts = parseFloat(info.minimum_point_to_redeem) || 0;
            const available = parseFloat(info.available_loyalty_points);

            if (!info.loyalty_enabled || !info.loyalty_applicable || rate <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Loyalty payment is not available for this sale.');
                } else {
                    alert('Loyalty payment is not available for this sale.');
                }
                return;
            }

            if (!Number.isFinite(available) || available + 1e-6 < minPts) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`You need at least ${minPts} loyalty points before you can redeem.`);
                } else {
                    alert(`You need at least ${minPts} loyalty points before you can redeem.`);
                }
                return;
            }

            const pointsRaw = parseFloat(String($('#payment-amount-input').val()).replace(/,/g, '')) || 0;

            if (pointsRaw <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Please enter a valid number of loyalty points.');
                } else {
                    alert('Please enter a valid number of loyalty points.');
                }
                return;
            }

            if (pointsRaw > available + 1e-6) {
                this.showLoyaltyPointsCapToast(available);
                $('#payment-amount-input').val(this.formatPointsForDisplay(available));
                return;
            }

            const maxPtsLine = this.getMaxLoyaltyPointsForLine();
            if (maxPtsLine <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Cannot redeem loyalty for this invoice (check remaining payable vs. points).');
                } else {
                    alert('Cannot redeem loyalty for this invoice (check remaining payable vs. points).');
                }
                return;
            }

            let finalPoints = Math.min(pointsRaw, maxPtsLine);
            finalPoints = parseFloat(parseFloat(finalPoints).toFixed(3));
            const finalAmt = parseFloat((finalPoints * rate).toFixed(4));

            if (finalPoints <= 0 || finalAmt <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`You cannot redeem more than ${this.formatPointsForDisplay(maxPtsLine)} points on this invoice.`);
                } else {
                    alert(`You cannot redeem more than ${this.formatPointsForDisplay(maxPtsLine)} points on this invoice.`);
                }
                return;
            }

            const usagePoint = finalPoints;

            const currentPaymentIdLp = this.currentPaymentMethod.id;
            this.addPaymentMethod(
                this.currentPaymentMethod.id,
                this.currentPaymentMethod.name,
                this.currentPaymentMethod.account_type,
                finalAmt,
                null,
                finalAmt,
                usagePoint
            );

            const nextRemLp = this.getRemainingPayable();
            this.currentPaymentMethod = null;
            $('.payment-method-btn').removeClass('btn-primary');
            $('.payment-method-btn').addClass('btn-outline-primary');
            this.selectNextPaymentMethod(currentPaymentIdLp);
            const prefLp = nextRemLp > 0 ? this.getNextPaymentPrefillAmount() : 0;
            if (prefLp > 0) {
                const loyaltyNext = !!(this.currentPaymentMethod && this.currentPaymentMethod.account_type === 'Loyalty Point');
                $('#payment-amount-input').val(loyaltyNext ? this.formatPointsForDisplay(prefLp) : this.formatNumber(prefLp));
            } else {
                $('#payment-amount-input').val('');
            }
            return;
        }

        // Regular payment methods (Cash, etc.)
        if (amount <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Please enter a valid payment amount');
            } else {
                alert('Please enter a valid payment amount');
            }
            return;
        }

        // Store current payment method ID before adding
        const currentPaymentId = this.currentPaymentMethod.id;

        // If Cash payment, use denomination total if available
        let finalAmount = amount;
        if (this.currentPaymentMethod.account_type === 'Cash') {
            const denominationTotal = this.getDenominationTotal();
            if (denominationTotal > 0) {
                finalAmount = denominationTotal;
            }
        }

        // Remember the amount the customer actually handed over BEFORE we clamp it to the remaining payable.
        // For Cash: given can exceed remaining (customer gives 130 for a 125 bill → change 5 is due).
        // For non-Cash: given == paid (clamped).
        const rawGivenAmount = finalAmount;

        finalAmount = this.clampPaymentLineAmount(finalAmount);
        if (finalAmount <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Payment amount cannot exceed remaining payable (${this.formatNumber(maxRemaining)}).`);
            } else {
                alert(`Payment amount cannot exceed remaining payable (${this.formatNumber(maxRemaining)}).`);
            }
            return;
        }

        // For non-cash methods, if the user typed more than remaining, keep behaviour identical to before:
        // clamp the given amount too (no change is tracked for card/online payments).
        const isCash = this.currentPaymentMethod.account_type === 'Cash';
        const lineGivenAmount = isCash ? rawGivenAmount : finalAmount;

        if (!isCash && finalAmount < rawGivenAmount - 0.0001) {
            $('#payment-amount-input').val(this.formatNumber(finalAmount));
            if (typeof showInfoNotification !== 'undefined') {
                showInfoNotification(`Amount adjusted to remaining payable (${this.formatNumber(finalAmount)}).`);
            }
        }

        this.addPaymentMethod(
            this.currentPaymentMethod.id,
            this.currentPaymentMethod.name,
            this.currentPaymentMethod.account_type,
            finalAmount,
            null,
            lineGivenAmount
        );

        const nextRem = this.getRemainingPayable();
        this.currentPaymentMethod = null;
        // Remove active class from payment method buttons
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');
        
        // Select next payment method (or current if only one)
        this.selectNextPaymentMethod(currentPaymentId);

        const pref = nextRem > 0 ? this.getNextPaymentPrefillAmount() : 0;
        if (pref > 0) {
            const loyaltyNext = !!(this.currentPaymentMethod && this.currentPaymentMethod.account_type === 'Loyalty Point');
            $('#payment-amount-input').val(loyaltyNext ? this.formatPointsForDisplay(pref) : this.formatNumber(pref));
        } else {
            $('#payment-amount-input').val('');
        }
    }

    /**
     * Check if payment method is already added
     */
    isPaymentMethodAdded(paymentId) {
        return this.selectedPayments.some(p => p.payment_id === paymentId);
    }

    /**
     * Add payment method
     *
     * @param {number|string} paymentId
     * @param {string}        paymentName
     * @param {string}        accountType
     * @param {number}        amount        Clamped amount actually applied to the invoice (<= remaining payable).
     * @param {object|null}   [gatewayData] Optional gateway info.
     * @param {number|null}   [givenAmount] Amount handed over by the customer. For cash, this may exceed
     *                                       the invoice's remaining payable; the excess becomes change.
     *                                       For non-cash methods, defaults to the clamped `amount`.
     * @param {number|null}   [usagePoint]   For Loyalty Point: decimal points deducted (optional; recomputed when omitted).
     */
    addPaymentMethod(paymentId, paymentName, accountType, amount, gatewayData = null, givenAmount = null, usagePoint = null) {
        // Check if already exists
        if (this.isPaymentMethodAdded(paymentId)) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`${paymentName} is already added. Please remove it first to add again.`);
            } else {
                alert(`${paymentName} is already added. Please remove it first to add again.`);
            }
            return;
        }

        let lineAmount;
        if (accountType === 'Loyalty Point') {
            const remainingCap = this.getRemainingPayable();
            const loyaltyCap = this.getMaxLoyaltyPayAmount();
            const n = parseFloat(amount) || 0;
            lineAmount = Math.min(n, remainingCap, loyaltyCap);
            lineAmount = parseFloat(lineAmount.toFixed(4));
        } else {
            lineAmount = this.clampPaymentLineAmount(amount);
        }
        if (lineAmount <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Payment amount cannot exceed remaining payable (${this.formatNumber(this.getRemainingPayable())}).`);
            } else {
                alert(`Payment amount cannot exceed remaining payable (${this.formatNumber(this.getRemainingPayable())}).`);
            }
            return;
        }

        // Resolve the line's given_amount. Only Cash is allowed to exceed the clamped amount (change due).
        let lineGiven = (givenAmount !== null && givenAmount !== undefined && !isNaN(parseFloat(givenAmount)))
            ? parseFloat(givenAmount)
            : lineAmount;
        if (accountType !== 'Cash' && lineGiven > lineAmount) {
            lineGiven = lineAmount;
        }
        if (lineGiven < lineAmount) {
            // given can never be less than the amount applied to the invoice
            lineGiven = lineAmount;
        }

        // Add to selected payments
        const payment = {
            payment_id: paymentId,
            payment_name: paymentName,
            account_type: accountType,
            amount: this.roundMoney(lineAmount),
            given_amount: this.roundMoney(lineGiven)
        };

        if (accountType === 'Loyalty Point') {
            const rate = parseFloat(this.lastCustomerLoyaltyInfo?.loyalty_rate) || 0;
            let up = usagePoint !== null && usagePoint !== undefined && !isNaN(parseFloat(usagePoint))
                ? parseFloat(usagePoint)
                : (rate > 0 ? parseFloat((lineAmount / rate).toFixed(3)) : 0);
            if (!Number.isFinite(up)) {
                up = 0;
            }
            payment.usage_point = up;
        }
        // Add gateway data if provided
        if (gatewayData) {
            payment.gateway_transaction_id = gatewayData.gateway_transaction_id;
            payment.gateway_name = gatewayData.gateway_name;
        }

        const useMulti = this.paymentLineUsesMultiCurrency(accountType, gatewayData);
        const mcRate = useMulti ? (parseFloat(this.activeCurrencyRate) || 0) : 1;
        const mcType = useMulti ? this.activeCurrencyType : null;
        payment.multi_currency = useMulti && mcRate > 0 && mcType ? 'Yes' : 'No';
        payment.multi_currency_rate = payment.multi_currency === 'Yes' ? mcRate : 1;
        payment.currency_type = payment.multi_currency === 'Yes' ? mcType : null;

        this.selectedPayments.push(payment);

        // Update UI
        this.renderPaymentSummary();
        this.updateRemainingAmount();
        this.resetDenomination();
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
    }

    /**
     * Select next payment method after adding a payment
     */
    selectNextPaymentMethod(currentPaymentId) {
        const self = this;
        
        // Get all payment method buttons (excluding "Change Currency" button)
        const $paymentButtons = $('.payment-method-btn').filter(function() {
            return $(this).data('payment-id') !== undefined && !$(this).hasClass('d-none');
        });
        
        // Filter out already added payment methods
        const availablePayments = [];
        $paymentButtons.each(function() {
            const paymentId = $(this).data('payment-id');
            if (!self.isPaymentMethodAdded(paymentId)) {
                availablePayments.push(this);
            }
        });
        
        // If no available payments, select current one (if only one method exists)
        if (availablePayments.length === 0) {
            // Find current payment method button and select it
            $paymentButtons.each(function() {
                if ($(this).data('payment-id') == currentPaymentId) {
                    $(this).trigger('click');
                    return false; // break
                }
            });
            return;
        }
        
        // Find index of current payment in available payments
        let currentIndex = -1;
        $(availablePayments).each(function(index) {
            if ($(this).data('payment-id') == currentPaymentId) {
                currentIndex = index;
                return false; // break
            }
        });
        
        // Select next payment method (or first if current was last or not found)
        let nextIndex = currentIndex + 1;
        if (nextIndex >= availablePayments.length || currentIndex === -1) {
            nextIndex = 0; // Wrap around to first
        }
        
        const $nextButton = $(availablePayments[nextIndex]);
        if ($nextButton.length > 0) {
            $nextButton.trigger('click');
        }
    }

    /**
     * Remove payment method
     */
    removePaymentMethod(paymentId) {
        this.selectedPayments = this.selectedPayments.filter(p => p.payment_id !== paymentId);
        this.renderPaymentSummary();
        this.updateRemainingAmount();
    }

    /**
     * Render payment summary table
     */
    renderPaymentSummary() {
        const $tbody = $('#payment-summary-tbody');
        const $emptyRow = $('#payment-summary-empty');

        if (this.selectedPayments.length === 0) {
            $emptyRow.show();
            $tbody.find('tr:not(#payment-summary-empty)').remove();
            return;
        }

        $emptyRow.hide();
        $tbody.find('tr:not(#payment-summary-empty)').remove();

        this.selectedPayments.forEach(payment => {
            const baseForRow = payment.multi_currency === 'Yes' && parseFloat(payment.multi_currency_rate) > 0
                ? parseFloat(payment.amount) / parseFloat(payment.multi_currency_rate)
                : parseFloat(payment.amount);
            let amtHtml = this.formatNumber(baseForRow);
            if (payment.multi_currency === 'Yes' && payment.currency_type) {
                amtHtml += ` <small class="text-muted">(${this.formatNumber(payment.amount)} ${this.escapeHtml(payment.currency_type)})</small>`;
            }
            const row = $(`
                <tr>
                    <td class="w-50">${this.escapeHtml(payment.payment_name)}</td>
                    <td class="w-25 text-center">${amtHtml}</td>
                    <td class="w-25 text-end">
                        <button type="button" class="btn btn-sm text-danger remove-payment-btn" data-payment-id="${payment.payment_id}">
                            <i class="icon-base ti tabler-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            $tbody.append(row);
        });
    }

    /**
     * Calculate total paid amount (sum of amounts applied to the invoice).
     */
    getTotalPaid() {
        const total = this.selectedPayments.reduce((sum, payment) => {
            const a = this.parsePosAmount(payment.amount);
            if (payment.multi_currency === 'Yes') {
                const r = this.parsePosAmount(payment.multi_currency_rate);
                return sum + (r > 0 ? a / r : a);
            }
            return sum + a;
        }, 0);
        return this.roundMoney(total);
    }

    /**
     * Calculate total given amount (sum of what the customer handed over across all lines).
     * For cash overpayments this can exceed total paid.
     */
    getTotalGiven() {
        const total = this.selectedPayments.reduce((sum, payment) => {
            const amount = this.parsePosAmount(payment.amount);
            const hasGiven = payment.given_amount !== undefined && payment.given_amount !== null;
            const g = hasGiven ? this.parsePosAmount(payment.given_amount) : amount;
            if (payment.multi_currency === 'Yes') {
                const r = this.parsePosAmount(payment.multi_currency_rate);
                return sum + (r > 0 ? g / r : g);
            }
            return sum + g;
        }, 0);
        return this.roundMoney(total);
    }

    /**
     * Change amount = total given - total paid (never negative).
     */
    getChangeAmount() {
        const change = this.getTotalGiven() - this.getTotalPaid();
        return change > 0 ? this.roundMoney(change) : 0;
    }

    /**
     * Whether the selected customer is Walk-in (no due sales allowed).
     */
    isWalkInCustomer() {
        const walkInCustomerId = $('#modal_pos_payment').data('walk-in-customer-id');
        const selectedCustomerId = $('#customer-select').val();
        if (walkInCustomerId === undefined || walkInCustomerId === '' || walkInCustomerId === null) {
            return false;
        }
        return String(selectedCustomerId) === String(walkInCustomerId);
    }

    /**
     * Show Loyalty Point payment button only when the customer is not Walk-in (loyalty disabled for walk-in in UI).
     */
    toggleLoyaltyPaymentButtons() {
        const walkIn = this.isWalkInCustomer();
        $('.payment-method-btn').each(function () {
            const accountType = $(this).data('account-type');
            if (accountType === 'Loyalty Point') {
                $(this).toggleClass('d-none', walkIn);
            }
        });
    }

    /**
     * Remove loyalty payment lines when switching to Walk-in.
     */
    removeLoyaltyPaymentsFromSummary() {
        const loyaltyIds = [];
        this.selectedPayments.forEach((p) => {
            if (p.account_type === 'Loyalty Point') {
                loyaltyIds.push(parseInt(p.payment_id, 10));
            }
        });
        loyaltyIds.forEach((id) => this.removePaymentMethod(id));
        if (this.currentPaymentMethod && this.currentPaymentMethod.account_type === 'Loyalty Point') {
            this.currentPaymentMethod = null;
            $('.payment-method-btn').removeClass('btn-primary');
            $('.payment-method-btn').addClass('btn-outline-primary');
            $('#gateway-payment-wrapper').hide();
            $('#denomination-section').hide();
        }
    }

    /**
     * Loyalty redemption: payable amount ≤ min(remaining, available_points × loyalty_rate).
     */
    getMaxLoyaltyPayAmount() {
        const info = this.lastCustomerLoyaltyInfo || {};
        const remaining = this.getRemainingPayable();
        if (!info.loyalty_applicable || !info.loyalty_enabled || (parseFloat(info.loyalty_rate) || 0) <= 0) {
            return 0;
        }
        const available = parseFloat(info.available_loyalty_points);
        const rate = parseFloat(info.loyalty_rate);
        if (!Number.isFinite(available) || !Number.isFinite(rate) || rate <= 0) {
            return 0;
        }
        const minPts = parseFloat(info.minimum_point_to_redeem) || 0;
        if (available + 1e-6 < minPts) {
            return 0;
        }
        return Math.min(remaining, available * rate);
    }

    /**
     * Max loyalty points redeemable this line (by balance & remaining invoice × rate).
     */
    getMaxLoyaltyPointsForLine() {
        const info = this.lastCustomerLoyaltyInfo || {};
        const remaining = this.getRemainingPayable();
        const rate = parseFloat(info.loyalty_rate) || 0;
        const available = parseFloat(info.available_loyalty_points);
        const minPts = parseFloat(info.minimum_point_to_redeem) || 0;
        if (!info.loyalty_applicable || !info.loyalty_enabled || rate <= 0) {
            return 0;
        }
        if (!Number.isFinite(available) || available + 1e-6 < minPts) {
            return 0;
        }
        if (remaining <= 0) {
            return 0;
        }
        const maxByInvoice = remaining / rate;
        return Math.min(available, maxByInvoice);
    }

    /**
     * Default value for payment input: currency amount, or loyalty points when Loyalty Point is selected.
     */
    getNextPaymentPrefillAmount() {
        const rem = this.getRemainingPayable();
        if (this.currentPaymentMethod && this.currentPaymentMethod.account_type === 'Loyalty Point' && !this.isWalkInCustomer()) {
            const maxPts = this.getMaxLoyaltyPointsForLine();
            return maxPts > 0 ? maxPts : 0;
        }
        return rem > 0 ? this.paymentInputFromBase(rem) : 0;
    }

    /**
     * Format point value for loyalty input / display (trim unnecessary decimals).
     */
    formatPointsForDisplay(val) {
        const n = parseFloat(val);
        if (!Number.isFinite(n)) {
            return '';
        }
        if (Math.abs(n % 1) < 1e-9) {
            return String(Math.round(n));
        }
        return String(parseFloat(n.toFixed(3)));
    }

    /**
     * Toast when entered points exceed customer's available balance (e.g. 101 vs 100).
     */
    showLoyaltyPointsCapToast(availablePoints) {
        const availStr = this.formatPointsForDisplay(availablePoints);
        const tpl = $('#modal_pos_payment').attr('data-points-exceed-template');
        let msg;
        if (tpl && tpl.includes('%s')) {
            msg = tpl.replace('%s', availStr).replace('%s', availStr);
        } else {
            const nNum = parseFloat(availStr);
            const pl = Number.isFinite(nNum) && nNum === 1 ? '' : 's';
            msg = `You have ${availStr} point${pl}. You cannot enter more than ${availStr} point${pl}.`;
        }
        if (typeof showErrorNotification !== 'undefined') {
            showErrorNotification(msg);
        } else {
            alert(msg);
        }
    }

    /**
     * Switch Payment Amount field between currency and loyalty points labeling.
     */
    updatePaymentAmountFieldMode() {
        const $modal = $('#modal_pos_payment');
        const isLoyalty = !!(this.currentPaymentMethod && this.currentPaymentMethod.account_type === 'Loyalty Point');
        const $label = $('#pos-payment-amount-label');
        const $hintDef = $('#pos-payment-amount-hint-default');
        const $hintLoyalty = $('#pos-payment-amount-hint-loyalty');
        const $input = $('#payment-amount-input');
        if (!$label.length) {
            return;
        }

        const amtLabel = $modal.attr('data-text-payment-amount-label') || 'Payment Amount';
        const ptsLabel = $modal.attr('data-text-loyalty-points-label') || 'Loyalty points';
        const phAmt = $modal.attr('data-text-placeholder-amount') || 'Enter amount';
        const phPts = $modal.attr('data-text-placeholder-points') || 'Enter points';

        if (isLoyalty) {
            $label.text(ptsLabel);
            $hintDef.hide();
            $hintLoyalty.show();
            $input.attr('placeholder', phPts);
            $input.attr('data-entry-mode', 'loyalty-points');
        } else {
            $label.text(amtLabel);
            $hintDef.show();
            $hintLoyalty.hide();
            $input.attr('placeholder', phAmt);
            $input.removeAttr('data-entry-mode');
        }
    }

    /**
     * Amount still allowed to be allocated across payment lines (cannot exceed total payable).
     */
    getRemainingPayable() {
        const paid = this.getTotalPaid();
        const rem = this.roundMoney(this.totalPayable - paid);
        return rem > 0 ? rem : 0;
    }

    /**
     * Clamp a payment line amount so total paid never exceeds total payable.
     */
    clampPaymentLineAmount(amount) {
        const n = this.parsePosAmount(amount);
        const maxBase = this.getRemainingPayable();
        if (maxBase <= 0) {
            return 0;
        }
        const maxInput = this.paymentInputFromBase(maxBase);
        return this.roundMoney(Math.min(n, maxInput));
    }

    /**
     * Update remaining amount
     */
    updateRemainingAmount() {
        const totalPaid = this.getTotalPaid();
        const totalGiven = this.getTotalGiven();
        const changeAmount = this.getChangeAmount();
        const remaining = this.roundMoney(this.totalPayable - totalPaid);

        $('#payment-summary-total').text(this.formatNumber(totalPaid));
        $('#payment-remaining-amount').text(this.formatNumber(remaining));

        // Given / Change rows — only show when an overpayment occurred (change > 0).
        const $givenRow = $('#payment-summary-given-row');
        const $changeRow = $('#payment-summary-change-row');
        if (changeAmount > 0) {
            $('#payment-summary-given').text(this.formatNumber(totalGiven));
            $('#payment-summary-change').text(this.formatNumber(changeAmount));
            $givenRow.show();
            $changeRow.show();
        } else {
            $givenRow.hide();
            $changeRow.hide();
        }

        // Highlight if overpaid or underpaid
        const $remainingCell = $('#payment-remaining-amount');
        $remainingCell.removeClass('text-success text-danger text-warning');
        
        if (remaining < 0) {
            $remainingCell.addClass('text-danger');
        } else if (remaining === 0) {
            $remainingCell.addClass('text-success');
        } else {
            $remainingCell.addClass('text-warning');
        }

        // Walk-in: no due sale; must pay exactly total payable (cannot complete with remaining > 0)
        const walkIn = this.isWalkInCustomer();
        const $dueBtn = $('#pos-due-sale-btn');
        const $completeBtn = $('#pos-payment-submit-btn');
        if (walkIn) {
            $dueBtn.hide();
            const fullyPaid = Math.abs(remaining) < 0.0001;
            $completeBtn.prop('disabled', !fullyPaid);
        } else {
            $dueBtn.show();
            $completeBtn.prop('disabled', false);
        }

        // Hint max for next line
        const maxNext = this.getRemainingPayable();
        $('#payment-amount-input').attr('data-max-remaining', maxNext > 0 ? String(maxNext) : '0');
    }

    /**
     * Calculate denomination total
     */
    calculateDenominationTotal() {
        let total = 0;
        const self = this;
        $('.denomination-input').each(function() {
            const count = self.parsePosAmount($(this).val());
            const value = self.parsePosAmount($(this).data('value'));
            total += count * value;
        });
        $('#denomination-total').text(this.formatNumber(total));
        return this.roundMoney(total);
    }

    /**
     * Get denomination total
     */
    getDenominationTotal() {
        return this.calculateDenominationTotal();
    }

    /**
     * Reset denomination inputs
     */
    resetDenomination() {
        $('.denomination-input').val(0);
        $('#denomination-total').text('0.00');
    }

    /**
     * Open payment modal
     */
    openPaymentModal(totalPayable) {
        this.totalPayable = this.roundMoney(this.parsePosAmount(totalPayable));
        $('#pos-payment-total').text(this.formatNumber(this.totalPayable));
        this.resetPaymentModal();
        
        // Ensure denomination section is hidden initially
        $('.payment-details-section').css('grid-template-columns', '3fr 2fr');
        
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_payment'));
        modal.show();
        
        // Note: Default payment method will be set in 'shown.bs.modal' event
    }

    /**
     * Show selected customer name, phone, and ledger balance (before this sale) above payment summary.
     */
    async refreshPaymentModalCustomerSummary() {
        const $name = $('#pos-payment-customer-name');
        const $phone = $('#pos-payment-customer-phone');
        const $prevDue = $('#pos-payment-customer-previous-due');
        const $loyaltyRow = $('#pos-payment-loyalty-row');
        const $loyaltyPts = $('#pos-payment-customer-loyalty-points');
        const $loyaltyHint = $('#pos-payment-loyalty-min-hint');
        if (!$name.length) {
            return;
        }

        const customerId = $('#customer-select').val();
        const walkInCustomerId = $('#modal_pos_payment').data('walk-in-customer-id');
        const $opt = $('#customer-select option:selected');

        if (!customerId) {
            $name.text('—');
            $phone.text('—');
            $prevDue.text(this.formatNumber(0));
            this.lastCustomerLoyaltyInfo = null;
            if ($loyaltyRow.length) {
                $loyaltyRow.hide();
            }
            return;
        }

        const displayName = ($opt.text() || '').trim() || '—';
        $name.text(displayName);

        if (walkInCustomerId !== undefined && walkInCustomerId !== '' && String(customerId) === String(walkInCustomerId)) {
            $phone.text('—');
            $prevDue.text(this.formatNumber(0));
            this.lastCustomerLoyaltyInfo = null;
            if ($loyaltyRow.length) {
                $loyaltyRow.hide();
            }
            return;
        }

        try {
            const baseUrl = $('#base_url').val() || window.location.origin;
            const response = await fetch(`${baseUrl}/pos/customer/${customerId}/credit-info`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (data.status === 'success') {
                this.lastCustomerLoyaltyInfo = data;
                if (data.name) {
                    $name.text(data.name);
                }
                const ph = (data.phone || '').trim();
                $phone.text(ph !== '' ? ph : '—');
                const prev = parseFloat(data.previous_due);
                $prevDue.text(this.formatNumber(Number.isFinite(prev) ? prev : 0));

                if ($loyaltyRow.length && $loyaltyPts.length) {
                    if (data.loyalty_enabled && data.loyalty_applicable) {
                        const avail = parseFloat(data.available_loyalty_points);
                        const minPts = parseFloat(data.minimum_point_to_redeem) || 0;
                        const rate = parseFloat(data.loyalty_rate) || 0;
                        $loyaltyPts.text(Number.isFinite(avail) ? this.formatNumber(avail) : '0');
                        $loyaltyRow.show();
                        if ($loyaltyHint.length) {
                            const parts = [];
                            if (Number.isFinite(minPts) && minPts > 0) {
                                parts.push(`${minPts} pts min. to redeem`);
                            }
                            if (rate > 0 && Number.isFinite(avail)) {
                                parts.push(`max ${this.formatNumber(avail * rate)} from points`);
                            }
                            if (parts.length) {
                                $loyaltyHint.text(parts.join(' · '));
                                $loyaltyHint.show();
                            } else {
                                $loyaltyHint.hide();
                            }
                        }
                    } else {
                        $loyaltyRow.hide();
                    }
                }

                return;
            }
        } catch (e) {
            console.warn('Could not load customer summary for payment modal:', e);
        }

        this.lastCustomerLoyaltyInfo = null;
        $phone.text('—');
        $prevDue.text(this.formatNumber(0));
        if ($loyaltyRow.length) {
            $loyaltyRow.hide();
        }
    }

    /**
     * Check if customer can have additional due amount (credit limit validation).
     * @param {string|number} customerId - Customer ID
     * @param {number} dueAmount - Amount that would be added as due
     * @returns {Promise<{allowed: boolean, message?: string}>}
     */
    async checkCustomerCreditLimit(customerId, dueAmount) {
        try {
            const baseUrl = $('#base_url').val() || window.location.origin;
            const response = await fetch(`${baseUrl}/pos/customer/${customerId}/credit-info`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.status !== 'success') {
                return { allowed: true }; // Allow if API fails (don't block sale)
            }
            const creditLimit = parseFloat(data.credit_limit) || 0;
            if (creditLimit <= 0) {
                return { allowed: true }; // No credit limit set
            }
            const availableCredit = parseFloat(data.available_credit) || 0;
            if (dueAmount <= availableCredit) {
                return { allowed: true };
            }
            const currentDue = parseFloat(data.current_due) || 0;
            return {
                allowed: false,
                message: `Customer credit limit exceeded. Credit limit: ${this.formatNumber(creditLimit)}, Current due: ${this.formatNumber(currentDue)}, Available credit: ${this.formatNumber(availableCredit)}. This sale would add ${this.formatNumber(dueAmount)} as due.`
            };
        } catch (e) {
            console.warn('Credit limit check failed:', e);
            return { allowed: true }; // Don't block on error
        }
    }

    /**
     * Reset payment modal
     */
    resetPaymentModal() {
        this.resetPaymentCurrencyToBase();
        this.selectedPayments = [];
        this.currentPaymentMethod = null;
        $('#payment-amount-input').val('');
        $('#pos-payment-currency-row').hide();
        this.resetDenomination();
        this.resetGatewayPayment();
        // Hide denomination section and reset grid layout
        $('.payment-details-section').css('grid-template-columns', '3fr 2fr');
        $('#gateway-payment-wrapper').hide();
        // Remove active class from payment method buttons
        $('.payment-method-btn').removeClass('active');
        this.renderPaymentSummary();
        this.updateRemainingAmount();
        this.updatePaymentAmountFieldMode();
    }

    /**
     * Submit payment
     * @param {boolean} isDueSale - If true, create sale as due (no payment collected). Walk-in Customer must be blocked by caller.
     */
    async submitPayment(isDueSale = false) {
        // Validation (skip payment requirement for due sale)
        if (!isDueSale && this.selectedPayments.length === 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Please add at least one payment method');
            } else {
                alert('Please add at least one payment method');
            }
            return;
        }

        const freshTotal = this.parsePosAmount($('#pos-total-amount').text());
        if (freshTotal > 0) {
            this.totalPayable = this.roundMoney(freshTotal);
            $('#pos-payment-total').text(this.formatNumber(this.totalPayable));
        }

        const totalPaid = isDueSale ? 0 : this.getTotalPaid();
        const remaining = this.roundMoney(this.totalPayable - totalPaid);
        const dueAmount = isDueSale ? this.totalPayable : (remaining > 0 ? remaining : 0);

        // Walk-in: must pay full amount in this session (no due, no underpayment)
        if (!isDueSale && this.isWalkInCustomer()) {
            if (remaining > 0.0001) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`Walk-in customers must pay the full amount. Remaining: ${this.formatNumber(remaining)}.`);
                } else {
                    alert(`Walk-in customers must pay the full amount. Remaining: ${this.formatNumber(remaining)}.`);
                }
                return;
            }
            if (remaining < -0.0001) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Total paid cannot exceed total payable for Walk-in Customer.');
                } else {
                    alert('Total paid cannot exceed total payable for Walk-in Customer.');
                }
                return;
            }
        }

        // Credit limit check: if customer has credit limit and due amount would exceed it, block
        if (dueAmount > 0) {
            const customerId = $('#customer-select').val() || null;
            const customerIdNum = customerId ? parseInt(customerId, 10) : NaN;
            if (customerId && !isNaN(customerIdNum) && customerIdNum > 0) {
                const creditCheck = await this.checkCustomerCreditLimit(customerId, dueAmount);
                if (!creditCheck.allowed) {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(creditCheck.message);
                    } else {
                        alert(creditCheck.message);
                    }
                    return;
                }
            }
        }

        // Warn if underpaid (but allow if user confirms) — not for due sale or Walk-in Customer
        if (!isDueSale && remaining > 0.0001 && !this.isWalkInCustomer()) {
            const confirmMsg = `Total paid (${this.formatNumber(totalPaid)}) is less than total payable (${this.formatNumber(this.totalPayable)}).
            Remaining: ${this.formatNumber(remaining)}.
            Do you want to proceed?`;
    
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: confirmMsg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            });
    
            if (!result.isConfirmed) {
                return;
            }
        }

    


        // Get cart data from POSCartManager
        if (typeof posCartManager === 'undefined') {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Cart manager not found');
            } else {
                alert('Cart manager not found');
            }
            return;
        }

        if (posCartManager.cartItems.length === 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Cart is empty. Please add items to cart.');
            } else {
                alert('Cart is empty. Please add items to cart.');
            }
            return;
        }

        const customerId = $('#customer-select').val() || null;
        const employeeId = $('#employee-select').val() || null;
        const subtotal = this.parsePosAmount($('#pos-subtotal').text());
        const tax = this.parsePosAmount($('#pos-tax').text());
        const shipping = posCartManager.cartSummaryShipping || this.parsePosAmount($('#pos-shipping-input').val());
        const discount = posCartManager.cartSummaryDiscount || 0;
        const discountType = posCartManager.cartSummaryDiscountType || 'fixed';
        const totalPayable = this.totalPayable;

        // Prepare cart items (include applicable_tax_id, tax_type for new GST backend calculation)
        const cartItems = posCartManager.cartItems.map(item => ({
            product_id: item.product_id,
            product_name: item.product_name,
            product_code: item.product_code,
            product_type: item.product_type,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount: item.discount || 0,
            discount_type: item.discount_type || 'fixed',
            tax_information: item.tax_information || [],
            applicable_tax_id: item.applicable_tax_id || null,
            tax_type: item.tax_type || 'Inclusive',
            selected_imei_serial: item.selected_imei_serial || [],
            selected_medicine_expiry: item.selected_medicine_expiry || [],
            item_seller_id: item.item_seller_id || null,
            combo_items: item.combo_items || [],
            is_promotion_free_item: item.is_promotion_free_item || false,
            promotion_id: item.promotion_id || (item.promotion && item.promotion.id) || null,
            promotion: item.promotion || null,
            has_promotion_discount: item.has_promotion_discount || false
        }));

        // Prepare payment data (empty for due sale)
        const payments = isDueSale ? [] : this.selectedPayments.map(payment => {
            const paymentData = {
                payment_id: payment.payment_id,
                payment_name: payment.payment_name,
                account_type: payment.account_type,
                amount: this.roundMoney(payment.amount),
                given_amount: this.roundMoney(
                    (payment.given_amount !== undefined && payment.given_amount !== null)
                        ? payment.given_amount
                        : payment.amount
                )
            };
            // Add gateway data if present
            if (payment.gateway_transaction_id) {
                paymentData.gateway_transaction_id = payment.gateway_transaction_id;
            }
            if (payment.gateway_name) {
                paymentData.gateway_name = payment.gateway_name;
            }
            if (payment.usage_point !== undefined && payment.usage_point !== null) {
                paymentData.usage_point = payment.usage_point;
            }
            paymentData.multi_currency = payment.multi_currency || 'No';
            paymentData.multi_currency_rate = payment.multi_currency === 'Yes'
                ? parseFloat(payment.multi_currency_rate) || 1
                : 1;
            paymentData.currency_type = payment.multi_currency === 'Yes' ? (payment.currency_type || null) : null;
            return paymentData;
        });

        const noteVal = ($('#pos-sale-note').val() || '').trim();
        const saleData = {
            customer_id: customerId ? parseInt(customerId) : null,
            employee_id: employeeId ? parseInt(employeeId) : null,
            cart_items: cartItems,
            subtotal: subtotal,
            tax: tax,
            discount: discount,
            discount_type: discountType,
            shipping: shipping,
            total_payable: this.roundMoney(totalPayable),
            payments: payments,
            total_paid: this.roundMoney(totalPaid),
            // given_amount = what the customer handed over across all lines (can exceed total_paid for cash change)
            given_amount: isDueSale ? 0 : this.roundMoney(this.getTotalGiven()),
            // change_amount = given - paid (cash overpayment change). Underpayment (remaining < 0 case) is still included for backward compatibility.
            change_amount: isDueSale
                ? 0
                : this.roundMoney(this.getChangeAmount() > 0 ? this.getChangeAmount() : (remaining < 0 ? Math.abs(remaining) : 0)),
            due_amount: this.roundMoney(isDueSale ? totalPayable : (remaining > 0 ? remaining : 0)),
            sale_as_due: isDueSale,
            send_email: $('#email').is(':checked'),
            send_sms: $('#sms').is(':checked'),
            send_whatsapp: $('#whatsapp').is(':checked'),
            sale_date: ($('#pos-sale-date').val() || '').trim() || new Date().toISOString().slice(0, 10),
            note: noteVal.length ? noteVal : null
        };

        // Check internet connection - but don't block if navigator.onLine is unreliable
        const internetConnected = window.internetConnected !== undefined ? window.internetConnected : navigator.onLine;
        
        // Only save offline if we're definitely offline AND the browser says so
        if (!internetConnected && !navigator.onLine) {
            // Save to IndexedDB for offline sync
            return await this.saveOfflineSale(saleData);
        }

        try {
            // Show loading
            if (typeof showInfoNotification !== 'undefined') {
                showInfoNotification('Processing payment...');
            }

            const baseUrl = $('#base_url').val() || window.location.origin;
            const isEditingSale = !!window.posEditingSaleId;
            const saveUrl = isEditingSale
                ? (baseUrl + route('sale.update', { sale: window.posEditingSaleId }, false, Ziggy))
                : `${baseUrl}/pos/sale`;
            const saveMethod = isEditingSale ? 'PUT' : 'POST';
            
            // Set a reasonable timeout (60 seconds) to handle ZATCA processing
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
            
            const response = await fetch(saveUrl, {
                method: saveMethod,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(saleData),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                if (isEditingSale) {
                    window.posEditingSaleId = null;
                    // Remove edit_sale from URL so it shows only /pos
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                }
                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification(isEditingSale ? 'Sale updated successfully! Sale No: ' + (result.sale_no || '') : `Sale completed successfully! Sale No: ${result.sale_no}`);
                } else {
                    alert(isEditingSale ? 'Sale updated successfully! Sale No: ' + (result.sale_no || '') : `Sale completed successfully! Sale No: ${result.sale_no}`);
                }

                // Open invoice popup window
                if (result.encrypted_id && typeof window.openInvoicePopup === 'function') {
                    window.openInvoicePopup(result.encrypted_id);
                }

                // Dismiss payment modal immediately so it is not stacked behind the invoice print window / heavy post-sale work.
                const paymentModal = bootstrap.Modal.getInstance(document.getElementById('modal_pos_payment'));
                if (paymentModal) {
                    paymentModal.hide();
                }

                // Update IndexedDB: remove IMEI/serial numbers and decrease stock (only for new sales, not edit)
                if (!isEditingSale && typeof posIndexedDB !== 'undefined' && cartItems.length > 0) {
                    try {
                        await posIndexedDB.updateProductsAfterSale(cartItems);
                        
                        const stockUiIds = typeof posIndexedDB.collectProductIdsForStockUiRefresh === 'function'
                            ? posIndexedDB.collectProductIdsForStockUiRefresh(cartItems)
                            : cartItems.map((x) => x.product_id);
                        for (const pid of stockUiIds) {
                            try {
                                const product = await posIndexedDB.getProductById(pid);
                                if (product && typeof posProductsDisplay !== 'undefined') {
                                    await posProductsDisplay.updateProductCardStock(pid, product.stock || 0);
                                }
                            } catch (error) {
                                console.error(`Error updating product card for product ${pid}:`, error);
                            }
                        }
                        
                        // Refresh product display to reflect updated stock
                        if (typeof posProductsDisplay !== 'undefined') {
                            // Trigger a refresh of the product grid
                            const currentCategoryId = posProductsDisplay.currentCategoryId;
                            const currentSearchTerm = posProductsDisplay.currentSearchTerm;
                            if (currentSearchTerm) {
                                await posProductsDisplay.handleSearch(currentSearchTerm);
                            } else if (currentCategoryId) {
                                await posProductsDisplay.handleCategoryClick(currentCategoryId, $(`.pos-category-item[data-category-id="${currentCategoryId}"], .pos-category-header[data-category-id="${currentCategoryId}"]`));
                            } else {
                                await posProductsDisplay.loadFirstChunk();
                            }
                        }
                    } catch (error) {
                        console.error('Error updating IndexedDB after sale:', error);
                        // Don't block the success flow if IndexedDB update fails
                    }
                }

                // Clear cart after successful save
                posCartManager.clearCart();
                $('#customer-select').val(this.company_session_data.default_customer).trigger('change');
                $('#employee-select').val(this.company_session_data.default_employee_id).trigger('change');
            } else {
                const errMsg = posExtractSaleApiMessage(result);
                if (result.error_code === 'subscription_invoice_limit') {
                    const msg = errMsg || 'Invoice limit reached.';
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(msg);
                    } else {
                        alert(msg);
                    }
                    return;
                }

                const retryable =
                    response.status >= 500 || response.status === 408 || response.status === 429;
                if (!isEditingSale && internetConnected && navigator.onLine && retryable) {
                    console.warn('Online save failed (retryable), attempting offline save:', errMsg || result.message);
                    return await this.saveOfflineSale(saleData);
                }

                // Show user-friendly error message
                let errorMessage = errMsg || 'Failed to process payment';
                
                // If error type is provided, show more specific message
                if (result.error_type === 'payment_gateway') {
                    errorMessage = result.message || 'Payment gateway error. Please check your payment method configuration.';
                } else if (result.error_type === 'database') {
                    errorMessage = result.message || 'Database error occurred. Please try again.';
                } else if (result.error_type === 'system') {
                    errorMessage = result.message || 'System error occurred. Please try again or contact support.';
                }
                
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(errorMessage);
                } else {
                    alert(errorMessage);
                }
            }
        } catch (error) {
            console.error('Error processing payment:', error);
            
            // Only save offline if it's a genuine network error, not a timeout
            if (error.name === 'AbortError') {
                // Request timed out - might be ZATCA processing taking too long
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Payment processing is taking longer than expected. Please check if the sale was saved.');
                } else {
                    alert('Payment processing is taking longer than expected. Please check if the sale was saved.');
                }
                return; // Don't save to IndexedDB - sale might have been saved
            }
            
            // If network error (not timeout), save offline
            if (error.message && (error.message.includes('fetch') || error.message.includes('network') || error.message.includes('Failed to fetch'))) {
                // Only save offline if we're actually offline
                if (!navigator.onLine) {
                    return await this.saveOfflineSale(saleData);
                }
            }
            
            // Show user-friendly error message
            let errorMessage = 'An error occurred while processing the payment.';
            
            if (error.message) {
                // Hide technical details from user
                if (error.message.includes('fetch') || error.message.includes('network')) {
                    errorMessage = 'Network error. Please check your internet connection and try again.';
                } else if (error.message.includes('timeout')) {
                    errorMessage = 'Request timeout. Please try again.';
                } else {
                    errorMessage = 'An error occurred while processing the payment. Please try again or contact support.';
                }
            }
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    }

    /**
     * Save sale to IndexedDB for offline sync
     * @param {Object} saleData - Sale data
     */
    async saveOfflineSale(saleData) {
        try {
            // Prepare sale_detail and sale_payment structure
            const saleDetail = {
                customer_id: saleData.customer_id,
                employee_id: saleData.employee_id,
                cart_items: saleData.cart_items,
                subtotal: saleData.subtotal,
                tax: saleData.tax,
                discount: saleData.discount,
                discount_type: saleData.discount_type,
                shipping: saleData.shipping,
                total_payable: saleData.total_payable,
                sale_as_due: !!saleData.sale_as_due,
                sale_date: saleData.sale_date,
                note: saleData.note
            };

            const salePayment = {
                payments: saleData.payments || [],
                total_paid: saleData.total_paid || saleData.total_payable,
                change_amount: saleData.change_amount || 0,
                due_amount: saleData.due_amount || 0
            };

            const offlineSaleData = {
                sale_detail: saleDetail,
                sale_payment: salePayment
            };

            // Save to IndexedDB
            if (typeof posIndexedDB !== 'undefined') {
                const saleId = await posIndexedDB.saveOfflineSale(offlineSaleData);
                
                // Update IndexedDB: remove IMEI/serial numbers and decrease stock (for offline tracking)
                if (saleData.cart_items && saleData.cart_items.length > 0) {
                    try {
                        await posIndexedDB.updateProductsAfterSale(saleData.cart_items);
                    } catch (error) {
                        console.error('Error updating IndexedDB products after offline sale:', error);
                    }
                }

                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification(`Sale saved offline! Will sync when internet is available. (ID: ${saleId})`);
                } else {
                    alert(`Sale saved offline! Will sync when internet is available. (ID: ${saleId})`);
                }

                // Generate and open offline invoice (no server needed)
                if (typeof POSOfflineInvoice !== 'undefined' && typeof POSOfflineInvoice.generateOfflineInvoice === 'function') {
                    const offlineRecord = { sale_detail: saleDetail, sale_payment: salePayment, created_at: new Date().toISOString(), synced: false };
                    POSOfflineInvoice.generateOfflineInvoice(offlineRecord, saleId, {
                        companySessionData: this.company_session_data,
                        customerName: $('#customer-select option:selected').text().trim(),
                        employeeName: $('#employee-select option:selected').text().trim(),
                        outletName: $('.outlet-name').text().trim()
                    });
                }

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modal_pos_payment'));
                if (modal) {
                    modal.hide();
                }

                // Clear cart after offline save
                posCartManager.clearCart();
                $('#customer-select').val(this.company_session_data.default_customer).trigger('change');
                $('#employee-select').val(this.company_session_data.default_employee_id).trigger('change');
            } else {
                throw new Error('IndexedDB not available');
            }
        } catch (error) {
            console.error('Error saving offline sale:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to save sale offline: ' + error.message);
            } else {
                alert('Failed to save sale offline: ' + error.message);
            }
        }
    }

    /**
     * Get gateway display title
     */
    getGatewayTitle(gatewayName) {
        const titles = {
            'stripe': 'Card Payment',
            'paypal': 'PayPal Payment',
            'razorpay': 'Razorpay Payment',
            'paystack': 'Paystack Payment',
            'paytm': 'Paytm Payment',
            'flutterwave': 'Flutterwave Payment',
            'sslcommerz': 'SSLCommerz Payment',
            'mollie': 'Mollie Payment',
            'senangpay': 'Senangpay Payment',
            'bkash': 'bKash Payment',
            'mercadopago': 'Mercado Pago Payment',
            'cashfree': 'Cashfree Payment',
            'payfast': 'Payfast Payment',
            'skrill': 'Skrill Payment',
            'phonepe': 'PhonePe Payment',
            'telr': 'Telr Payment',
            'iyzico': 'Iyzico Payment',
            'pesapal': 'Pesapal Payment',
            'midtrans': 'Midtrans Payment',
            'myfatoorah': 'MyFatoorah Payment',
            'easypaisa': 'EasyPaisa Payment',
            'mpesa': 'Mpesa Payment'
        };
        return titles[gatewayName.toLowerCase()] || `${gatewayName.charAt(0).toUpperCase() + gatewayName.slice(1)} Payment`;
    }

    /**
     * Initialize gateway payment (generic method)
     */
    async initializeGatewayPayment(paymentMethodId, gatewayName) {
        // Route to gateway-specific initialization
        switch (gatewayName.toLowerCase()) {
            case 'stripe':
                await this.initializeStripePayment(paymentMethodId, gatewayName);
                break;
            case 'paypal':
                await this.initializePayPalPayment(paymentMethodId, gatewayName);
                break;
            case 'razorpay':
                await this.initializeRazorpayPayment(paymentMethodId, gatewayName);
                break;
            case 'paytm':
                await this.initializePaytmPayment(paymentMethodId, gatewayName);
                break;
            case 'paystack':
                await this.initializePaystackPayment(paymentMethodId, gatewayName);
                break;
            case 'flutterwave':
                await this.initializeFlutterwavePayment(paymentMethodId, gatewayName);
                break;
            case 'myfatoorah':
                await this.initializeMyFatoorahPayment(paymentMethodId, gatewayName);
                break;
            case 'mpesa':
                await this.initializeMpesaPayment(paymentMethodId, gatewayName);
                break;
            // Add more gateways here as needed
            default:
                console.warn(`Gateway ${gatewayName} initialization not implemented yet`);
                $('#gateway-payment-element').html(`
                    <div class="alert alert-warning">
                        <p>Gateway "${gatewayName}" is not yet implemented in the frontend.</p>
                        <p>Please contact support or use a different payment method.</p>
                    </div>
                `);
        }
    }

    /**
     * Process gateway payment (generic method)
     */
    async processGatewayPayment(amount, gatewayName) {
        // Route to gateway-specific processing
        switch (gatewayName.toLowerCase()) {
            case 'stripe':
                await this.processStripePayment(amount);
                break;
            case 'paypal':
                await this.processPayPalPayment(amount);
                break;
            case 'razorpay':
                await this.processRazorpayPayment(amount);
                break;
            case 'paytm':
                await this.processPaytmPayment(amount);
                break;
            case 'paystack':
                await this.processPaystackPayment(amount);
                break;
            case 'flutterwave':
                await this.processFlutterwavePayment(amount);
                break;
            case 'myfatoorah':
                await this.processMyFatoorahPayment(amount);
                break;
            case 'mpesa':
                await this.processMpesaPayment(amount);
                break;
            // Add more gateways here as needed
            default:
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`Payment processing for ${gatewayName} is not yet implemented`);
                } else {
                    alert(`Payment processing for ${gatewayName} is not yet implemented`);
                }
        }
    }

    /**
     * Confirm gateway payment (generic method)
     */
    async confirmGatewayPayment(gatewayName) {
        // Route to gateway-specific confirmation
        switch (gatewayName.toLowerCase()) {
            case 'stripe':
                await this.confirmStripePayment();
                break;
            case 'paypal':
                await this.confirmPayPalPayment();
                break;
            case 'razorpay':
                await this.confirmRazorpayPayment();
                break;
            case 'paytm':
                await this.confirmPaytmPayment();
                break;
            case 'paystack':
                await this.confirmPaystackPayment();
                break;
            case 'flutterwave':
                await this.confirmFlutterwavePayment();
                break;
            case 'myfatoorah':
                await this.confirmMyFatoorahPayment();
                break;
            case 'mpesa':
                await this.confirmMpesaPayment();
                break;
            // Add more gateways here as needed
            default:
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`Payment confirmation for ${gatewayName} is not yet implemented`);
                } else {
                    alert(`Payment confirmation for ${gatewayName} is not yet implemented`);
                }
        }
    }

    /**
     * Initialize Stripe payment
     */
    async initializeStripePayment(paymentMethodId, gatewayName) {
        try {
            // Get Stripe publishable key from backend
            const baseUrl = $('#base_url').val() || window.location.origin;
            // Pass payment_method_id as query parameter to get configuration from payment method
            const configUrl = `${baseUrl}/payment-gateway/${gatewayName}/config?payment_method_id=${paymentMethodId}`;
            const configResponse = await fetch(configUrl, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                }
            });

            if (!configResponse.ok) {
                throw new Error(`HTTP error! status: ${configResponse.status}`);
            }

            const configData = await configResponse.json();
            
            // Log full response for debugging
            console.log('Stripe config response:', configData);
            
            if (configData.status !== 'success') {
                throw new Error(configData.message || 'Failed to get Stripe configuration');
            }

            // Get publishable key from public_config
            // Try different possible key names and locations
            const publicConfig = configData.data?.public_config || configData.data || {};
            
            // Try multiple possible key names
            const publishableKey = publicConfig.publishable_key || 
                                   publicConfig.api_key || 
                                   publicConfig.public_key ||
                                   publicConfig.key ||
                                   publicConfig.stripe_api_key ||
                                   publicConfig.stripe_publishable_key ||
                                   (publicConfig.stripe && publicConfig.stripe.publishable_key) ||
                                   (publicConfig.stripe && publicConfig.stripe.api_key);
            
            if (!publishableKey) {
                // Log the full config structure for debugging
                console.error('Stripe configuration structure:', {
                    fullResponse: configData,
                    publicConfig: publicConfig,
                    dataKeys: configData.data ? Object.keys(configData.data) : []
                });
                throw new Error('Stripe publishable key not found in configuration. Please check your payment method configuration.');
            }

            // Initialize Stripe
            if (!window.Stripe) {
                // Load Stripe.js if not already loaded
                await this.loadStripeJS();
            }

            this.stripe = Stripe(publishableKey);

            // Show ready message - form will load when amount is entered
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Stripe Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with card payment.</p>
                </div>
            `);

        } catch (error) {
            console.error('Error initializing Stripe:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                paymentMethodId: paymentMethodId,
                gatewayName: gatewayName
            });
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Stripe: ' + error.message);
            } else {
                alert('Failed to initialize Stripe: ' + error.message);
            }
        }
    }

    /**
     * Load Stripe.js library
     */
    loadStripeJS() {
        return new Promise((resolve, reject) => {
            if (window.Stripe) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://js.stripe.com/v3/';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load Stripe.js'));
            document.head.appendChild(script);
        });
    }

    /**
     * Process Stripe payment
     */
    async processStripePayment(amount) {
        try {
            if (!this.stripe) {
                await this.initializeStripePayment(this.currentPaymentMethod.id, this.currentPaymentMethod.gateway_name);
            }

            // Show loading in the payment element
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Initializing payment form...</p>
                </div>
            `);

            // Initialize payment on backend
            const baseUrl = $('#base_url').val() || window.location.origin;
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'USD',
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: $('#customer-select').val() || null,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.client_secret) {
                throw new Error(result.message || 'Failed to initialize payment');
            }

            const clientSecret = result.data.client_secret;
            const transactionId = result.data.transaction_id;

            // Create Stripe Elements
            this.stripeElements = this.stripe.elements({
                clientSecret: clientSecret,
                appearance: {
                    theme: 'stripe',
                }
            });

            // Create and mount payment element
            if (this.stripePaymentElement) {
                this.stripePaymentElement.unmount();
                this.stripePaymentElement = null;
            }

            // Clear the element before mounting
            $('#gateway-payment-element').empty();

            this.stripePaymentElement = this.stripeElements.create('payment');
            this.stripePaymentElement.mount('#gateway-payment-element');
            
            // Verify element was mounted
            if (!$('#gateway-payment-element').children().length) {
                throw new Error('Failed to mount Stripe payment element');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: transactionId,
                clientSecret: clientSecret,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'stripe'
            };

            // Show confirm button
            $('#gateway-confirm-payment-btn').show();
            $('#gateway-confirm-btn-text').text('Confirm Payment');

        } catch (error) {
            console.error('Error processing Stripe payment:', error);
            
            // Show error in payment element
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryStripePayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to process payment: ' + error.message);
            } else {
                alert('Failed to process payment: ' + error.message);
            }
        }
    }

    /**
     * Retry Stripe payment (called from error message)
     */
    async retryStripePayment(amount) {
        await this.processStripePayment(amount);
    }

    /**
     * Confirm Stripe payment
     */
    async confirmStripePayment() {
        try {
            if (!this.stripe || !this.stripePaymentElement || !this.currentGatewayPayment) {
                throw new Error('Payment not initialized');
            }

            // Show loading
            $('#gateway-confirm-payment-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

            // Store currentGatewayPayment reference before any async operations
            const gatewayPaymentInfo = this.currentGatewayPayment;
            
            if (!gatewayPaymentInfo) {
                throw new Error('Payment information is missing. Please reinitialize the payment.');
            }
            
            // Retrieve PaymentIntent status first to check if it can be confirmed
            try {
                const {paymentIntent: currentIntent} = await this.stripe.retrievePaymentIntent(
                    gatewayPaymentInfo.clientSecret
                );
                
                // If already succeeded, handle success
                if (currentIntent.status === 'succeeded') {
                    this.handleStripePaymentSuccess(currentIntent, gatewayPaymentInfo);
                    return;
                }
                
                // If in a final state that doesn't allow confirmation, throw error
                if (['canceled', 'processing'].includes(currentIntent.status)) {
                    throw new Error(`PaymentIntent is in ${currentIntent.status} state and cannot be confirmed`);
                }
            } catch (retrieveError) {
                console.warn('Could not retrieve PaymentIntent status:', retrieveError);
                // Continue with confirmation attempt
            }

            // Confirm payment
            // Note: When using Stripe Elements with automatic_payment_methods,
            // the payment method is collected automatically by the Elements
            const {error, paymentIntent} = await this.stripe.confirmPayment({
                elements: this.stripeElements,
                confirmParams: {
                    return_url: window.location.href,
                },
                redirect: 'if_required'
            }).catch(err => {
                // Log detailed error for debugging
                console.error('Stripe confirmPayment error details:', {
                    error: err,
                    message: err.message,
                    code: err.code,
                    payment_intent: err.payment_intent,
                    client_secret: gatewayPaymentInfo?.clientSecret
                });
                
                // If error has payment_intent, check its status
                if (err.payment_intent) {
                    const intentStatus = err.payment_intent.status;
                    if (intentStatus === 'succeeded') {
                        // Payment actually succeeded, handle it
                        this.handleStripePaymentSuccess(err.payment_intent, gatewayPaymentInfo);
                        return {error: null, paymentIntent: err.payment_intent};
                    }
                }
                
                throw err;
            });

            if (error) {
                throw new Error(error.message);
            }

            if (paymentIntent) {
                if (paymentIntent.status === 'succeeded') {
                    this.handleStripePaymentSuccess(paymentIntent, gatewayPaymentInfo);
                } else if (paymentIntent.status === 'requires_action') {
                    // Payment requires additional action (3D Secure, etc.)
                    // Stripe Elements will handle this automatically with redirect: 'if_required'
                    throw new Error('Payment requires additional authentication. Please complete the authentication.');
                } else {
                    throw new Error(`Payment status: ${paymentIntent.status}`);
                }
            } else {
                throw new Error('Payment not completed');
            }

        } catch (error) {
            console.error('Error confirming Stripe payment:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Payment failed: ' + error.message);
            } else {
                alert('Payment failed: ' + error.message);
            }
            $('#gateway-confirm-payment-btn').prop('disabled', false).html('<i class="icon-base ti tabler-credit-card me-1"></i><span>Confirm Payment</span>');
        }
    }

    /**
     * Handle successful Stripe payment
     * @param {Object} paymentIntent - Stripe PaymentIntent object
     * @param {Object} gatewayPaymentInfo - Optional payment info (falls back to this.currentGatewayPayment)
     */
    handleStripePaymentSuccess(paymentIntent, gatewayPaymentInfo = null) {
        // Use provided gatewayPaymentInfo or fall back to this.currentGatewayPayment
        const paymentInfo = gatewayPaymentInfo || this.currentGatewayPayment;
        
        // Check if we have payment method information
        if (!paymentInfo) {
            console.error('Payment info is null when handling success');
            // Try to get payment method info from currentPaymentMethod
            if (this.currentPaymentMethod) {
                // Use currentPaymentMethod as fallback
                this.addPaymentMethod(
                    this.currentPaymentMethod.id,
                    this.currentPaymentMethod.name,
                    'Gateway',
                    paymentIntent.amount ? paymentIntent.amount / 100 : 0,
                    {
                        gateway_transaction_id: paymentIntent.id,
                        gateway_name: 'stripe'
                    }
                );
            } else {
                console.error('Cannot handle payment success: missing payment method information');
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Payment succeeded but could not process payment details. Please refresh the page.');
                }
                return;
            }
        } else {
            // Payment succeeded - add to payment list
            this.addPaymentMethod(
                paymentInfo.paymentMethodId,
                paymentInfo.paymentMethodName,
                'Gateway',
                paymentInfo.amount,
                {
                    gateway_transaction_id: paymentIntent.id,
                    gateway_name: 'stripe'
                }
            );
        }

        // Reset Stripe
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Payment processed successfully!');
        }

        // Select next payment method (only if we have the payment method ID)
        const paymentMethodId = paymentInfo?.paymentMethodId || this.currentPaymentMethod?.id;
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Initialize PayPal payment
     */
    async initializePayPalPayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message - form will load when amount is entered
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-brand-paypal mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>PayPal Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with PayPal payment.</p>
                </div>
            `);
            
            // Hide confirm button initially (PayPal uses redirect, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();
        } catch (error) {
            console.error('Error initializing PayPal:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
        }
    }

    /**
     * Process PayPal payment
     */
    async processPayPalPayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating PayPal order...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return and cancel URLs
            const returnUrl = `${baseUrl}/pos/payment/paypal/return?payment_method_id=${this.currentPaymentMethod.id}&amount=${amount}`;
            const cancelUrl = `${baseUrl}/pos/payment/paypal/cancel?payment_method_id=${this.currentPaymentMethod.id}`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'USD',
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl,
                    cancel_url: cancelUrl,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.redirect_url) {
                throw new Error(result.message || 'Failed to initialize PayPal payment');
            }

            const redirectUrl = result.data.redirect_url;
            const transactionId = result.data.transaction_id;

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: transactionId,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'paypal',
                redirectUrl: redirectUrl
            };

            // Open PayPal in a popup window
            const popup = window.open(
                redirectUrl,
                'PayPal Payment',
                'width=600,height=700,scrollbars=yes,resizable=yes'
            );

            if (!popup) {
                throw new Error('Popup blocked. Please allow popups for this site and try again.');
            }

            // Monitor popup for completion
            this.monitorPayPalPopup(popup, transactionId, amount);
            
            // Also listen for postMessage from popup
            this.setupPayPalMessageListener(transactionId, amount);

            // Show message to user
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-brand-paypal mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Redirecting to PayPal...</strong></p>
                    <p class="mb-2 small">A new window has opened for PayPal payment. Please complete the payment there.</p>
                    <p class="mb-0 small text-muted">If the window didn't open, <a href="${redirectUrl}" target="_blank" class="alert-link">click here</a> to open it manually.</p>
                </div>
            `);

        } catch (error) {
            console.error('Error processing PayPal payment:', error);
            
            // Show error in payment element
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryPayPalPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to process payment: ' + error.message);
            } else {
                alert('Failed to process payment: ' + error.message);
            }
        }
    }

    /**
     * Retry PayPal payment (called from error message)
     */
    async retryPayPalPayment(amount) {
        await this.processPayPalPayment(amount);
    }

    /**
     * Setup message listener for PayPal popup communication
     */
    setupPayPalMessageListener(transactionId, amount) {
        const self = this;
        const messageHandler = function(event) {
            // Verify origin for security (in production, check actual domain)
            // For now, accept from any origin since PayPal redirects can vary
            if (event.data && event.data.type) {
                if (event.data.type === 'paypal_payment_success') {
                    // Payment succeeded
                    self.checkPayPalPaymentStatus(transactionId, amount);
                    window.removeEventListener('message', messageHandler);
                } else if (event.data.type === 'paypal_payment_failed' || event.data.type === 'paypal_payment_cancelled') {
                    // Payment failed or cancelled
                    $('#gateway-payment-element').html(`
                        <div class="alert alert-warning">
                            <strong>Payment ${event.data.type === 'paypal_payment_cancelled' ? 'Cancelled' : 'Failed'}:</strong> ${event.data.message || 'Payment was not completed'}
                            <br><br>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.retryPayPalPayment(${amount})">
                                Try Again
                            </button>
                        </div>
                    `);
                    window.removeEventListener('message', messageHandler);
                }
            }
        };
        
        window.addEventListener('message', messageHandler);
        
        // Clean up listener after 10 minutes
        setTimeout(() => {
            window.removeEventListener('message', messageHandler);
        }, 600000);
    }

    /**
     * Monitor PayPal popup window for completion
     */
    monitorPayPalPopup(popup, transactionId, amount) {
        const self = this;
        const checkInterval = setInterval(() => {
            try {
                // Check if popup is closed
                if (popup.closed) {
                    clearInterval(checkInterval);
                    // Check payment status after popup closes
                    self.checkPayPalPaymentStatus(transactionId, amount);
                } else {
                    // Check if popup has navigated to return URL (same origin)
                    try {
                        const popupUrl = popup.location.href;
                        if (popupUrl && popupUrl.includes('/pos/payment/paypal/return')) {
                            // Popup has returned, close it and check status
                            popup.close();
                            clearInterval(checkInterval);
                            self.checkPayPalPaymentStatus(transactionId, amount);
                        }
                    } catch (e) {
                        // Cross-origin error - popup is still on PayPal domain, continue monitoring
                    }
                }
            } catch (e) {
                // Error accessing popup - might be closed or cross-origin
                if (popup.closed) {
                    clearInterval(checkInterval);
                    self.checkPayPalPaymentStatus(transactionId, amount);
                }
            }
        }, 1000); // Check every second

        // Timeout after 10 minutes
        setTimeout(() => {
            clearInterval(checkInterval);
            if (!popup.closed) {
                popup.close();
            }
        }, 600000);
    }

    /**
     * Check PayPal payment status after redirect
     */
    async checkPayPalPaymentStatus(transactionId, amount) {
        try {
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Verifying payment...</p>
                </div>
            `);

            // Check payment status
            const response = await fetch(`${baseUrl}/pos/payment/paypal/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    transaction_id: transactionId,
                    payment_method_id: this.currentPaymentMethod.id
                })
            });

            const result = await response.json();

            if (result.status === 'success' && result.data.is_success) {
                // Payment succeeded
                this.handlePayPalPaymentSuccess(result.data, amount);
            } else {
                // Payment failed or pending
                throw new Error(result.message || 'Payment verification failed');
            }

        } catch (error) {
            console.error('Error checking PayPal payment status:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-warning">
                    <strong>Payment Status:</strong> Unable to verify payment automatically.
                    <br><br>
                    <p class="mb-2">Transaction ID: <code>${transactionId}</code></p>
                    <p class="mb-2">Please check your PayPal account or contact support.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.checkPayPalPaymentStatus('${transactionId}', ${amount})">
                        Retry Verification
                    </button>
                </div>
            `);
        }
    }

    /**
     * Handle successful PayPal payment
     */
    handlePayPalPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.order_id;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'paypal'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('PayPal payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm PayPal payment (not used for redirect-based flow, but kept for consistency)
     */
    async confirmPayPalPayment() {
        // PayPal uses redirect flow, so confirmation happens automatically
        // This method is kept for consistency with other gateways
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('PayPal payment will be confirmed after you complete the payment on PayPal.');
        }
    }

    /**
     * Initialize Razorpay payment
     */
    async initializeRazorpayPayment(paymentMethodId, gatewayName) {
        try {
            // Get Razorpay key_id from backend
            const baseUrl = $('#base_url').val() || window.location.origin;
            const configUrl = `${baseUrl}/payment-gateway/${gatewayName}/config?payment_method_id=${paymentMethodId}`;
            const configResponse = await fetch(configUrl, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                }
            });

            if (!configResponse.ok) {
                throw new Error(`HTTP error! status: ${configResponse.status}`);
            }

            const configData = await configResponse.json();
            
            if (configData.status !== 'success') {
                throw new Error(configData.message || 'Failed to get Razorpay configuration');
            }

            // Get key_id from public_config
            const publicConfig = configData.data?.public_config || configData.data || {};
            const keyId = publicConfig.key_id || publicConfig.api_key || publicConfig.public_key || publicConfig.key;

            if (!keyId) {
                throw new Error('Razorpay key_id not found in configuration. Please check your payment method configuration.');
            }

            // Store key_id for later use
            this.razorpayKeyId = keyId;

            // Load Razorpay checkout script if not already loaded
            if (!window.Razorpay) {
                await this.loadRazorpayJS();
            }

            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Razorpay Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with Razorpay payment.</p>
                </div>
            `);

            // Hide confirm button initially (Razorpay uses checkout modal, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing Razorpay:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Razorpay: ' + error.message);
            } else {
                alert('Failed to initialize Razorpay: ' + error.message);
            }
        }
    }

    /**
     * Load Razorpay.js library
     */
    loadRazorpayJS() {
        return new Promise((resolve, reject) => {
            if (window.Razorpay) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load Razorpay.js'));
            document.head.appendChild(script);
        });
    }

    /**
     * Process Razorpay payment
     */
    async processRazorpayPayment(amount) {
        try {
            if (!this.razorpayKeyId) {
                await this.initializeRazorpayPayment(this.currentPaymentMethod.id, this.currentPaymentMethod.gateway_name);
            }

            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating Razorpay order...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Get customer details if available
            let customerEmail = null;
            let customerName = null;
            let customerPhone = null;
            
            if (customerId) {
                const customerSelect = $('#customer-select');
                const selectedOption = customerSelect.find('option:selected');
                customerName = selectedOption.text() || null;
                // You might need to fetch customer details from an API if email/phone are not in the select
            }

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'INR', // Razorpay uses INR
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize Razorpay payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const orderId = gatewayResponse.razorpay_order_id || gatewayResponse.order_id;
            const transactionId = result.data.transaction_id;
            const keyId = gatewayResponse.key_id || this.razorpayKeyId;

            if (!orderId) {
                throw new Error('Razorpay order ID not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: transactionId,
                orderId: orderId,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'razorpay',
                keyId: keyId
            };

            // Prepare Razorpay options
            const razorpayOptions = {
                key: keyId,
                amount: Math.round(amount * 100), // Convert to paise (smallest currency unit)
                currency: 'INR',
                name: 'POS Payment',
                description: `Payment for ${this.currentPaymentMethod.name}`,
                order_id: orderId,
                handler: (response) => {
                    // Payment successful - verify payment
                    this.verifyRazorpayPayment(response, amount);
                },
                prefill: {
                    name: customerName || '',
                    email: customerEmail || '',
                    contact: customerPhone || ''
                },
                theme: {
                    color: '#3399cc'
                },
                modal: {
                    ondismiss: () => {
                        // User closed the modal
                        $('#gateway-payment-element').html(`
                            <div class="alert alert-warning">
                                <strong>Payment Cancelled:</strong> You closed the payment window.
                                <br><br>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.retryRazorpayPayment(${amount})">
                                    Try Again
                                </button>
                            </div>
                        `);
                    }
                }
            };

            // Create Razorpay instance and open checkout
            const razorpay = new Razorpay(razorpayOptions);
            razorpay.open();

            // Show message to user
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Razorpay Checkout Opened</strong></p>
                    <p class="mb-2 small">Please complete the payment in the Razorpay window.</p>
                    <p class="mb-0 small text-muted">If the window didn't open, please check your popup blocker settings.</p>
                </div>
            `);

        } catch (error) {
            console.error('Error processing Razorpay payment:', error);
            
            // Show error in payment element
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryRazorpayPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to process payment: ' + error.message);
            } else {
                alert('Failed to process payment: ' + error.message);
            }
        }
    }

    /**
     * Retry Razorpay payment (called from error message)
     */
    async retryRazorpayPayment(amount) {
        await this.processRazorpayPayment(amount);
    }

    /**
     * Verify Razorpay payment after successful checkout
     */
    async verifyRazorpayPayment(razorpayResponse, amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Verifying payment...</p>
                </div>
            `);

            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Verify payment on backend
            const response = await fetch(`${baseUrl}/payment-gateway/razorpay/verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    razorpay_order_id: razorpayResponse.razorpay_order_id,
                    razorpay_payment_id: razorpayResponse.razorpay_payment_id,
                    razorpay_signature: razorpayResponse.razorpay_signature,
                    payment_method_id: this.currentPaymentMethod.id
                })
            });

            const result = await response.json();

            if (result.status === 'success' && (result.is_success === true || result.data?.is_success === true)) {
                // Payment verified successfully
                this.handleRazorpayPaymentSuccess(result, amount);
            } else {
                // Payment verification failed
                throw new Error(result.message || 'Payment verification failed');
            }

        } catch (error) {
            console.error('Error verifying Razorpay payment:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Verification Failed:</strong> ${this.escapeHtml(error.message)}
                    <br><br>
                    <p class="mb-2">Transaction ID: <code>${razorpayResponse.razorpay_payment_id}</code></p>
                    <p class="mb-2">Please check your Razorpay account or contact support.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.verifyRazorpayPayment(${JSON.stringify(razorpayResponse)}, ${amount})">
                        Retry Verification
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Payment verification failed: ' + error.message);
            } else {
                alert('Payment verification failed: ' + error.message);
            }
        }
    }

    /**
     * Handle successful Razorpay payment
     */
    handleRazorpayPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || 
                              paymentData.data?.razorpay_payment_id || 
                              paymentData.data?.payment_id ||
                              paymentData.razorpay_payment_id || 
                              paymentData.payment_id;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'razorpay'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Razorpay payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm Razorpay payment (not used, but kept for consistency)
     */
    async confirmRazorpayPayment() {
        // Razorpay uses checkout modal flow, so confirmation happens automatically
        // This method is kept for consistency with other gateways
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('Razorpay payment will be confirmed after you complete the payment in the Razorpay checkout.');
        }
    }

    /**
     * Initialize Paytm payment
     */
    async initializePaytmPayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Paytm Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with Paytm payment.</p>
                </div>
            `);

            // Hide confirm button (Paytm uses form redirect, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing Paytm:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Paytm: ' + error.message);
            } else {
                alert('Failed to initialize Paytm: ' + error.message);
            }
        }
    }

    /**
     * Process Paytm payment
     */
    async processPaytmPayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating Paytm transaction...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return URL for Paytm callback
            const returnUrl = `${baseUrl}/pos/payment/paytm/callback`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'INR', // Paytm uses INR
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl, // Add return URL for Paytm callback
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize Paytm payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const redirectUrl = result.data.redirect_url || gatewayResponse.redirect_url;
            const orderId = gatewayResponse.order_id || result.data.transaction_id;
            const txnToken = gatewayResponse.txn_token;

            if (!redirectUrl) {
                throw new Error('Paytm payment URL not found in response');
            }

            if (!txnToken) {
                throw new Error('Paytm transaction token not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: result.data.transaction_id,
                orderId: orderId,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'paytm',
                redirectUrl: redirectUrl,
                txnToken: txnToken
            };

            // Show message to user
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Redirecting to Paytm...</strong></p>
                    <p class="mb-0 small">You will be redirected to Paytm payment page to complete the payment.</p>
                </div>
            `);

            // Redirect to Paytm payment page
            // Use window.location for full page redirect (Paytm requires this)
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);

        } catch (error) {
            console.error('Error processing Paytm payment:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryPaytmPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to process payment: ' + error.message);
            } else {
                alert('Failed to process payment: ' + error.message);
            }
        }
    }

    /**
     * Check Paytm payment status after return from Paytm
     * This is called when user returns from Paytm payment page
     */
    async checkPaytmPaymentStatus(transactionId, amount) {
        try {
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Verifying payment...</p>
                </div>
            `);

            // Check payment status from backend
            const response = await fetch(`${baseUrl}/payment-gateway/paytm/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    transaction_id: transactionId,
                    payment_method_id: this.currentPaymentMethod.id
                })
            });

            const result = await response.json();

            if (result.status === 'success' && result.data.is_success) {
                // Payment succeeded
                this.handlePaytmPaymentSuccess(result.data, amount);
            } else {
                // Payment failed or pending
                throw new Error(result.message || 'Payment verification failed');
            }

        } catch (error) {
            console.error('Error checking Paytm payment status:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-warning">
                    <strong>Payment Status:</strong> Unable to verify payment automatically.
                    <br><br>
                    <p class="mb-2">Transaction ID: <code>${transactionId}</code></p>
                    <p class="mb-2">Please check your Paytm account or contact support.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.checkPaytmPaymentStatus('${transactionId}', ${amount})">
                        Retry Verification
                    </button>
                </div>
            `);
        }
    }

    /**
     * Retry Paytm payment (called from error message)
     */
    async retryPaytmPayment(amount) {
        await this.processPaytmPayment(amount);
    }

    /**
     * Handle successful Paytm payment
     */
    handlePaytmPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.order_id;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'paytm'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Paytm payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm Paytm payment
     */
    async confirmPaytmPayment() {
        // Paytm uses redirect flow, so confirmation happens after callback
        // This method is kept for consistency with other gateways
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('Paytm payment will be confirmed after you complete the payment on Paytm.');
        }
    }

    /**
     * Initialize Paystack payment
     */
    async initializePaystackPayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Paystack Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with Paystack payment.</p>
                </div>
            `);

            // Hide confirm button (Paystack uses redirect, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing Paystack:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Paystack: ' + error.message);
            } else {
                alert('Failed to initialize Paystack: ' + error.message);
            }
        }
    }

    /**
     * Process Paystack payment
     */
    async processPaystackPayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating Paystack transaction...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return URL for Paystack callback
            const returnUrl = `${baseUrl}/pos/payment/paystack/callback`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'NGN', // Paystack uses NGN, ZAR, GHS, USD
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize Paystack payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const redirectUrl = result.data.redirect_url || gatewayResponse.authorization_url;
            const reference = gatewayResponse.reference || result.data.transaction_id;

            if (!redirectUrl) {
                throw new Error('Paystack payment URL not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: result.data.transaction_id,
                reference: reference,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'paystack',
                redirectUrl: redirectUrl
            };

            // Redirect to Paystack payment page
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);

        } catch (error) {
            console.error('Error processing Paystack payment:', error);
            
            // Show user-friendly error
            let errorMessage = 'Failed to process Paystack payment.';
            if (error.message) {
                if (error.message.includes('credential') || error.message.includes('not configured')) {
                    errorMessage = 'Paystack credentials are not configured correctly. Please check your payment method settings.';
                } else if (error.message.includes('timeout') || error.message.includes('connection')) {
                    errorMessage = 'Paystack connection error. Please check your internet connection and try again.';
                } else {
                    errorMessage = error.message;
                }
            }
            
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryPaystackPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    }

    /**
     * Retry Paystack payment
     */
    async retryPaystackPayment(amount) {
        await this.processPaystackPayment(amount);
    }

    /**
     * Handle successful Paystack payment
     */
    handlePaystackPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.reference;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'paystack'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Paystack payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm Paystack payment
     */
    async confirmPaystackPayment() {
        // Paystack uses redirect flow, so confirmation happens after callback
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('Paystack payment will be confirmed after you complete the payment on Paystack.');
        }
    }

    /**
     * Initialize Flutterwave payment
     */
    async initializeFlutterwavePayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Flutterwave Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with Flutterwave payment.</p>
                </div>
            `);

            // Hide confirm button (Flutterwave uses redirect, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing Flutterwave:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Flutterwave: ' + error.message);
            } else {
                alert('Failed to initialize Flutterwave: ' + error.message);
            }
        }
    }

    /**
     * Process Flutterwave payment
     */
    async processFlutterwavePayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating Flutterwave transaction...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return URL for Flutterwave callback
            const returnUrl = `${baseUrl}/pos/payment/flutterwave/callback`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'NGN', // Flutterwave supports multiple currencies
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize Flutterwave payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const redirectUrl = result.data.redirect_url || gatewayResponse.link;
            const txRef = gatewayResponse.tx_ref || result.data.transaction_id;

            if (!redirectUrl) {
                throw new Error('Flutterwave payment URL not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: result.data.transaction_id,
                txRef: txRef,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'flutterwave',
                redirectUrl: redirectUrl
            };

            // Redirect to Flutterwave payment page
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);

        } catch (error) {
            console.error('Error processing Flutterwave payment:', error);
            
            // Show user-friendly error
            let errorMessage = 'Failed to process Flutterwave payment.';
            if (error.message) {
                if (error.message.includes('credential') || error.message.includes('not configured')) {
                    errorMessage = 'Flutterwave credentials are not configured correctly. Please check your payment method settings.';
                } else if (error.message.includes('timeout') || error.message.includes('connection')) {
                    errorMessage = 'Flutterwave connection error. Please check your internet connection and try again.';
                } else {
                    errorMessage = error.message;
                }
            }
            
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryFlutterwavePayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    }

    /**
     * Retry Flutterwave payment
     */
    async retryFlutterwavePayment(amount) {
        await this.processFlutterwavePayment(amount);
    }

    /**
     * Handle successful Flutterwave payment
     */
    handleFlutterwavePaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.tx_ref;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'flutterwave'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Flutterwave payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm Flutterwave payment
     */
    async confirmFlutterwavePayment() {
        // Flutterwave uses redirect flow, so confirmation happens after callback
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('Flutterwave payment will be confirmed after you complete the payment on Flutterwave.');
        }
    }

    /**
     * Initialize MyFatoorah payment
     */
    async initializeMyFatoorahPayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-credit-card mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>MyFatoorah Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with MyFatoorah payment.</p>
                </div>
            `);

            // Hide confirm button (MyFatoorah uses redirect, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing MyFatoorah:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize MyFatoorah: ' + error.message);
            } else {
                alert('Failed to initialize MyFatoorah: ' + error.message);
            }
        }
    }

    /**
     * Process MyFatoorah payment
     */
    async processMyFatoorahPayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Creating MyFatoorah invoice...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return URL for MyFatoorah callback
            const returnUrl = `${baseUrl}/pos/payment/myfatoorah/callback`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'SAR', // MyFatoorah supports multiple currencies (SAR, KWD, AED, etc.)
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize MyFatoorah payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const redirectUrl = result.data.redirect_url || gatewayResponse.invoice_url;
            const invoiceId = gatewayResponse.invoice_id || result.data.transaction_id;

            if (!redirectUrl) {
                throw new Error('MyFatoorah payment URL not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: result.data.transaction_id,
                invoiceId: invoiceId,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'myfatoorah',
                redirectUrl: redirectUrl
            };

            // Redirect to MyFatoorah payment page
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);

        } catch (error) {
            console.error('Error processing MyFatoorah payment:', error);
            
            // Show user-friendly error
            let errorMessage = 'Failed to process MyFatoorah payment.';
            if (error.message) {
                if (error.message.includes('credential') || error.message.includes('not configured')) {
                    errorMessage = 'MyFatoorah credentials are not configured correctly. Please check your payment method settings.';
                } else if (error.message.includes('timeout') || error.message.includes('connection')) {
                    errorMessage = 'MyFatoorah connection error. Please check your internet connection and try again.';
                } else {
                    errorMessage = error.message;
                }
            }
            
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryMyFatoorahPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    }

    /**
     * Retry MyFatoorah payment
     */
    async retryMyFatoorahPayment(amount) {
        await this.processMyFatoorahPayment(amount);
    }

    /**
     * Handle successful MyFatoorah payment
     */
    handleMyFatoorahPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.invoice_id;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'myfatoorah'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('MyFatoorah payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm MyFatoorah payment
     */
    async confirmMyFatoorahPayment() {
        // MyFatoorah uses redirect flow, so confirmation happens after callback
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('MyFatoorah payment will be confirmed after you complete the payment on MyFatoorah.');
        }
    }

    /**
     * Initialize Mpesa payment
     */
    async initializeMpesaPayment(paymentMethodId, gatewayName) {
        try {
            // Show ready message
            $('#gateway-payment-element').html(`
                <div class="alert alert-info text-center p-4">
                    <i class="icon-base ti tabler-device-mobile mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>Mpesa Payment Ready</strong></p>
                    <p class="mb-0 small">Please enter the payment amount above and click "Add" to proceed with Mpesa STK Push payment.</p>
                </div>
            `);

            // Hide confirm button (Mpesa uses STK Push, not a confirm button)
            $('#gateway-confirm-payment-btn').hide();

        } catch (error) {
            console.error('Error initializing Mpesa:', error);
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(error.message)}
                </div>
            `);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to initialize Mpesa: ' + error.message);
            } else {
                alert('Failed to initialize Mpesa: ' + error.message);
            }
        }
    }

    /**
     * Process Mpesa payment
     */
    async processMpesaPayment(amount) {
        try {
            // Show loading
            $('#gateway-payment-element').html(`
                <div class="text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Sending Mpesa STK Push...</p>
                </div>
            `);

            // Get customer information
            const customerId = $('#customer-select').val() || null;
            const baseUrl = $('#base_url').val() || window.location.origin;
            
            // Create return URL for Mpesa callback
            const returnUrl = `${baseUrl}/pos/payment/mpesa/callback`;

            // Initialize payment on backend
            const response = await fetch(`${baseUrl}/pos/payment-gateway/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    gateway: this.currentPaymentMethod.gateway_name,
                    amount: amount,
                    currency: 'KES', // Mpesa uses KES
                    description: `POS Payment - ${this.currentPaymentMethod.name}`,
                    payment_method_id: this.currentPaymentMethod.id,
                    customer_id: customerId,
                    return_url: returnUrl,
                })
            });

            const result = await response.json();

            if (result.status !== 'success' || !result.data.gateway_response) {
                throw new Error(result.message || 'Failed to initialize Mpesa payment');
            }

            const gatewayResponse = result.data.gateway_response;
            const checkoutRequestId = gatewayResponse.checkout_request_id || result.data.transaction_id;
            const customerMessage = gatewayResponse.customer_message || 'Please check your phone to complete the payment';

            if (!checkoutRequestId) {
                throw new Error('Mpesa checkout request ID not found in response');
            }

            // Store current gateway payment info
            this.currentGatewayPayment = {
                transactionId: result.data.transaction_id,
                checkoutRequestId: checkoutRequestId,
                amount: amount,
                paymentMethodId: this.currentPaymentMethod.id,
                paymentMethodName: this.currentPaymentMethod.name,
                gatewayName: 'mpesa',
            };

            // Show success message and start polling for payment status
            $('#gateway-payment-element').html(`
                <div class="alert alert-success text-center p-4">
                    <i class="icon-base ti tabler-device-mobile mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-2"><strong>STK Push Sent!</strong></p>
                    <p class="mb-2">${customerMessage}</p>
                    <p class="mb-0 small">Please check your phone and enter your Mpesa PIN to complete the payment.</p>
                    <div class="mt-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Waiting for payment...</span>
                        </div>
                        <p class="mt-2 small">Waiting for payment confirmation...</p>
                    </div>
                </div>
            `);

            // Start polling for payment status
            this.pollMpesaPaymentStatus(checkoutRequestId, amount);

        } catch (error) {
            console.error('Error processing Mpesa payment:', error);
            
            // Show user-friendly error
            let errorMessage = 'Failed to process Mpesa payment.';
            if (error.message) {
                if (error.message.includes('credential') || error.message.includes('not configured')) {
                    errorMessage = 'Mpesa credentials are not configured correctly. Please check your payment method settings.';
                } else if (error.message.includes('phone') || error.message.includes('PhoneNumber')) {
                    errorMessage = 'Phone number is required for Mpesa payment. Please ensure customer has a phone number.';
                } else if (error.message.includes('timeout') || error.message.includes('connection')) {
                    errorMessage = 'Mpesa connection error. Please check your internet connection and try again.';
                } else {
                    errorMessage = error.message;
                }
            }
            
            $('#gateway-payment-element').html(`
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
                    <br><br>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="posPaymentManager.retryMpesaPayment(${amount})">
                        Retry
                    </button>
                </div>
            `);
            
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
            } else {
                alert(errorMessage);
            }
        }
    }

    /**
     * Poll Mpesa payment status
     */
    async pollMpesaPaymentStatus(checkoutRequestId, amount) {
        const maxAttempts = 30; // Poll for 5 minutes (30 * 10 seconds)
        let attempts = 0;
        
        const pollInterval = setInterval(async () => {
            attempts++;
            
            try {
                const baseUrl = $('#base_url').val() || window.location.origin;
                
                const response = await fetch(`${baseUrl}/payment-gateway/mpesa/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction_id: checkoutRequestId,
                        payment_method_id: this.currentPaymentMethod.id
                    })
                });

                const result = await response.json();

                if (result.status === 'success' && result.data.is_success) {
                    clearInterval(pollInterval);
                    this.handleMpesaPaymentSuccess(result.data, amount);
                } else if (result.status === 'success' && result.data.status === 'failed') {
                    clearInterval(pollInterval);
                    throw new Error(result.data.message || 'Payment failed');
                } else if (attempts >= maxAttempts) {
                    clearInterval(pollInterval);
                    $('#gateway-payment-element').html(`
                        <div class="alert alert-warning">
                            <strong>Payment Status:</strong> Payment verification timeout.
                            <br><br>
                            <p class="mb-2">Checkout Request ID: <code>${checkoutRequestId}</code></p>
                            <p class="mb-2">Please check your Mpesa account or contact support.</p>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="posPaymentManager.pollMpesaPaymentStatus('${checkoutRequestId}', ${amount})">
                                Retry Verification
                            </button>
                        </div>
                    `);
                }
            } catch (error) {
                console.error('Error polling Mpesa payment status:', error);
                if (attempts >= maxAttempts) {
                    clearInterval(pollInterval);
                }
            }
        }, 10000); // Poll every 10 seconds
    }

    /**
     * Retry Mpesa payment
     */
    async retryMpesaPayment(amount) {
        await this.processMpesaPayment(amount);
    }

    /**
     * Handle successful Mpesa payment
     */
    handleMpesaPaymentSuccess(paymentData, amount) {
        const transactionId = paymentData.transaction_id || paymentData.mpesa_receipt_number || paymentData.checkout_request_id;
        
        // Store payment method info before clearing
        const paymentMethodId = this.currentPaymentMethod?.id;
        const paymentMethodName = this.currentPaymentMethod?.name;
        
        // Add payment to payment list
        this.addPaymentMethod(
            paymentMethodId,
            paymentMethodName,
            'Gateway',
            amount,
            {
                gateway_transaction_id: transactionId,
                gateway_name: 'mpesa'
            }
        );

        // Reset gateway payment
        this.resetGatewayPayment();

        // Clear current payment method
        $('#payment-amount-input').val('');
        this.currentPaymentMethod = null;
        $('.payment-method-btn').removeClass('btn-primary');
        $('.payment-method-btn').addClass('btn-outline-primary');

        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification('Mpesa payment processed successfully!');
        }

        // Select next payment method
        if (paymentMethodId) {
            this.selectNextPaymentMethod(paymentMethodId);
        }
    }

    /**
     * Confirm Mpesa payment
     */
    async confirmMpesaPayment() {
        // Mpesa uses STK Push flow, so confirmation happens automatically
        if (typeof showInfoNotification !== 'undefined') {
            showInfoNotification('Mpesa payment will be confirmed after you complete the payment on your phone.');
        }
    }

    /**
     * Reset gateway payment
     */
    resetGatewayPayment() {
        // Reset Stripe if initialized
        if (this.stripePaymentElement) {
            this.stripePaymentElement.unmount();
            this.stripePaymentElement = null;
        }
        this.stripeElements = null;
        this.stripe = null;
        // Reset Razorpay
        this.razorpayKeyId = null;
        // Reset Paytm
        this.currentGatewayPayment = null;
        $('#gateway-payment-element').empty();
        $('#gateway-confirm-payment-btn').hide();
    }

    /**
     * Format number - show decimals only if needed
     */
    formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) {
            return '0.00';
        }
        const numValue = parseFloat(num);
        return numValue.toFixed(2);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }
}

// Initialize global instance
const posPaymentManager = new POSPaymentManager();

// Update POSCartManager to use payment modal
$(document).ready(function() {
    // Override openPaymentModal in POSCartManager if it exists
    if (typeof posCartManager !== 'undefined') {
        const originalOpenPaymentModal = posCartManager.openPaymentModal;
        posCartManager.openPaymentModal = function() {
            // Validation
            if (this.cartItems.length === 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Cart is empty. Please add items to cart.');
                } else {
                    alert('Cart is empty. Please add items to cart.');
                }
                return;
            }

            // Update total in payment modal
            const totalPayable = posPaymentManager.parsePosAmount($('#pos-total-amount').text());
            
            // Open payment modal using POSPaymentManager
            if (typeof posPaymentManager !== 'undefined') {
                posPaymentManager.openPaymentModal(totalPayable);
            }
        };
    }
});
