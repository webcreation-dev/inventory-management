(function (window, $) {
    'use strict';

    const Printer = {
        getConfig() {
            return window.PrintManagerConfig || {};
        },

        populatePrinterSelect(selectSelector, printers, selectedPrinter, emptyLabel) {
            const $select = $(selectSelector);
            const current = selectedPrinter || $select.val() || '';
            const placeholder = emptyLabel
                || $select.find('option[value=""]').first().text()
                || 'Select Printer';

            $select.empty();
            $select.append($('<option>', { value: '', text: placeholder }));

            (printers || []).forEach(function (printer) {
                const name = typeof printer === 'string' ? printer : (printer && (printer.name || printer));
                if (!name) {
                    return;
                }

                $select.append($('<option>', {
                    value: name,
                    text: name,
                    selected: name === current
                }));
            });

            if (current && !$select.find('option').filter(function () {
                return $(this).val() === current;
            }).length) {
                $select.append($('<option>', {
                    value: current,
                    text: current,
                    selected: true
                }));
            }
        },

        refreshPrinters(selectSelector, statusSelector) {
            const self = this;
            const config = this.getConfig();
            const $status = statusSelector ? $(statusSelector) : $();
            const deferred = $.Deferred();

            if ($status.length) {
                $status
                    .removeClass('alert-success alert-danger text-success text-danger')
                    .addClass('alert-info text-muted')
                    .text('Connecting to QZ Tray...');
            }

            PrintManagerQZ.findPrinters()
                .then(function (printers) {
                    self.populatePrinterSelect(selectSelector, printers, config.selectedPrinter);

                    if ($status.length) {
                        $status
                            .removeClass('alert-info alert-danger text-muted text-danger')
                            .addClass('alert-success text-success')
                            .text('QZ Tray connected. ' + (printers || []).length + ' printer(s) found.');
                    }

                    deferred.resolve(printers);
                })
                .catch(function (error) {
                    const message = error || 'QZ Tray is not running.';

                    if ($status.length) {
                        $status
                            .removeClass('alert-info alert-success text-muted text-success')
                            .addClass('alert-danger text-danger')
                            .text(message);
                    }

                    deferred.reject(message);
                });

            return deferred.promise();
        },

        loadInstalledPrinters(containerSelector) {
            const $container = $(containerSelector);

            return PrintManagerQZ.findPrinters()
                .then(function (printers) {
                    if (!printers.length) {
                        $container.html('<div class="text-muted">No printers detected.</div>');
                        return printers;
                    }

                    const html = ['<ul class="list-group">'];
                    printers.forEach(function (printer) {
                        html.push('<li class="list-group-item d-flex justify-content-between align-items-center">'
                            + $('<div>').text(printer).html()
                            + '<span class="badge bg-label-primary">Installed</span></li>');
                    });
                    html.push('</ul>');
                    $container.html(html.join(''));
                    return printers;
                })
                .catch(function (error) {
                    $container.html('<div class="text-danger">' + (error || 'Unable to connect to QZ Tray.') + '</div>');
                    return $.Deferred().reject(error).promise();
                });
        },

        browserPrintHtml(html) {
            const printWindow = window.open('', '_blank', 'width=800,height=600');

            if (!printWindow) {
                return $.Deferred().reject('Popup blocked. Allow popups to use browser print.').promise();
            }

            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title></head><body>' + html + '</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();

            return $.Deferred().resolve(true).promise();
        },

        executeJob(job) {
            if (!job) {
                return $.Deferred().reject('Print job payload is missing.').promise();
            }

            if (job.driver === 'browser' || job.instructions?.action === 'browser_print') {
                return this.browserPrintHtml(job.data || '');
            }

            if (job.driver === 'qz_tray' || job.instructions?.action === 'qz_print') {
                return this.printWithQz(job);
            }

            if (job.fallback) {
                return this.browserPrintHtml(job.data || '');
            }

            return $.Deferred().reject('Unsupported print driver.').promise();
        },

        printWithQz(job) {
            const printer = job.printer;
            const copies = parseInt(job.copies || 1, 10);
            const data = [];

            if (!printer) {
                return $.Deferred().reject('No default printer configured.').promise();
            }

            if (typeof qz === 'undefined' || typeof qz.configs === 'undefined') {
                return $.Deferred().reject('QZ Tray library is not loaded.').promise();
            }

            switch (job.type) {
                case 'html':
                    data.push({ type: 'html', format: 'plain', data: job.data });
                    break;
                case 'pdf':
                    data.push({ type: 'pdf', format: 'base64', data: job.data });
                    break;
                case 'text':
                    data.push({ type: 'raw', format: 'plain', data: job.data });
                    break;
                case 'raw':
                default:
                    data.push({ type: 'raw', format: 'base64', data: job.data });
                    break;
            }

            const config = qz.configs.create(printer, {
                copies: copies > 0 ? copies : 1,
                encoding: job.encoding || 'UTF-8'
            });

            return PrintManagerQZ.connect()
                .then(function () {
                    return qz.print(config, data);
                })
                .then(function () {
                    if (job.cash_drawer) {
                        return qz.print(qz.configs.create(printer, {
                            encoding: job.encoding || 'UTF-8'
                        }), [{
                            type: 'raw',
                            format: 'base64',
                            data: job.cash_drawer
                        }]);
                    }
                    return true;
                });
        },

        updatePrintLog(logId, status, errorMessage) {
            const config = this.getConfig();

            if (!logId || !config.routes?.log) {
                return $.Deferred().resolve().promise();
            }

            return $.ajax({
                url: config.routes.log,
                method: 'POST',
                data: {
                    _token: config.csrfToken,
                    log_id: logId,
                    status: status,
                    error_message: errorMessage || ''
                }
            });
        }
    };

    window.PrintManagerPrinter = Printer;
})(window, jQuery);
