(function (window, $) {
    'use strict';

    const ReceiptPrinter = {
        normalizeError(error) {
            if (!error) {
                return 'Test print failed.';
            }

            if (typeof error === 'string') {
                return error;
            }

            if (error.responseJSON && error.responseJSON.message) {
                return error.responseJSON.message;
            }

            if (error.message) {
                return error.message;
            }

            return 'Test print failed.';
        },

        getConfig() {
            return window.PrintManagerConfig || {};
        },

        printReceipt(receiptData, options) {
            const config = this.getConfig();

            return $.ajax({
                url: options?.url || config.routes?.receipt,
                method: 'POST',
                data: {
                    _token: config.csrfToken,
                    module: options?.module || 'PrintManager',
                    branch_id: options?.branch_id || '',
                    copies: options?.copies || '',
                    open_cash_drawer: options?.open_cash_drawer ? 1 : 0,
                    receipt: receiptData
                }
            }).then(function (response) {
                if (!response.success) {
                    return $.Deferred().reject(response.message || 'Unable to prepare receipt print job.').promise();
                }

                return ReceiptPrinter.executePreparedJob(response);
            });
        },

        printHtml(html, options) {
            const config = this.getConfig();

            return $.ajax({
                url: options?.url || config.routes?.html,
                method: 'POST',
                data: {
                    _token: config.csrfToken,
                    module: options?.module || 'PrintManager',
                    branch_id: options?.branch_id || '',
                    html: html
                }
            }).then(function (response) {
                if (!response.success) {
                    return $.Deferred().reject(response.message || 'Unable to prepare HTML print job.').promise();
                }

                return ReceiptPrinter.executePreparedJob(response);
            });
        },

        printRaw(raw, options) {
            const config = this.getConfig();

            return $.ajax({
                url: options?.url || config.routes?.raw,
                method: 'POST',
                data: {
                    _token: config.csrfToken,
                    module: options?.module || 'PrintManager',
                    branch_id: options?.branch_id || '',
                    raw: raw,
                    encoding: options?.encoding || 'plain',
                    open_cash_drawer: options?.open_cash_drawer ? 1 : 0
                }
            }).then(function (response) {
                if (!response.success) {
                    return $.Deferred().reject(response.message || 'Unable to prepare raw print job.').promise();
                }

                return ReceiptPrinter.executePreparedJob(response);
            });
        },

        executePreparedJob(response) {
            const logId = response.log_id;

            return PrintManagerPrinter.executeJob(response.payload)
                .then(function () {
                    return PrintManagerPrinter.updatePrintLog(logId, 'success');
                })
                .then(function () {
                    return response;
                })
                .catch(function (error) {
                    return PrintManagerPrinter.updatePrintLog(logId, 'failed', error)
                        .always(function () {
                            return $.Deferred().reject(error).promise();
                        });
                });
        },

        runTestPrint(formData) {
            const config = this.getConfig();

            return $.ajax({
                url: config.routes?.test,
                method: 'POST',
                data: formData
            }).then(function (response) {
                if (!response.success) {
                    return $.Deferred().reject(response.message || 'Test print failed to prepare.').promise();
                }

                return ReceiptPrinter.executePreparedJob(response);
            }, function (xhr) {
                const message = ReceiptPrinter.normalizeError(xhr);
                return $.Deferred().reject(message).promise();
            });
        }
    };

    window.PrintManagerReceipt = ReceiptPrinter;

    $(function () {
        $('#printTestForm').on('submit', function (event) {
            event.preventDefault();

            const $btn = $('#runTestPrintBtn');
            const $status = $('#testStatus');

            $btn.prop('disabled', true);
            $status.removeClass('alert-danger alert-success').addClass('alert-info').text('Preparing test print...');

            PrintManagerReceipt.runTestPrint($(this).serialize())
                .done(function () {
                    $status.removeClass('alert-info alert-danger').addClass('alert-success')
                        .text('Test print sent successfully.');
                })
                .fail(function (error) {
                    $status.removeClass('alert-info alert-success').addClass('alert-danger')
                        .text(ReceiptPrinter.normalizeError(error));
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(window, jQuery);
