(function (window, $) {
    'use strict';

    $(function () {
        if ($('#printSettingsForm').length === 0) {
            return;
        }

        if ($('#branch_id').length && $.fn.select2) {
            $('#branch_id').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        PrintManagerPrinter.refreshPrinters('#default_printer', '#qzConnectionStatus');

        if (typeof PrintManagerQZ !== 'undefined' && PrintManagerQZ.getSecurityStatus) {
            PrintManagerQZ.getSecurityStatus().done(function (response) {
                if (!response || !response.security) {
                    return;
                }
                const sec = response.security;
                let msg = 'Mode: ' + sec.env;
                if (sec.certificate_configured && sec.private_key_configured) {
                    msg += ' | Certificate & key OK | Algorithm: ' + sec.signature_algorithm;
                    $('#qzConnectionStatus').removeClass('text-danger text-muted').addClass('text-success').text(msg);
                } else if (sec.env === 'development') {
                    msg += ' | Unsigned (configure storage/app/qz/ keys for silent print)';
                    $('#qzConnectionStatus').removeClass('text-success text-danger').addClass('text-warning').text(msg);
                } else {
                    msg += ' | Missing certificate or private key in storage/app/qz/';
                    $('#qzConnectionStatus').removeClass('text-success text-muted').addClass('text-danger').text(msg);
                }
            });
        }

        $('#refreshPrintersBtn').on('click', function () {
            PrintManagerPrinter.refreshPrinters('#default_printer', '#qzConnectionStatus');
        });

        $('#branch_id').on('change', function () {
            const branchId = $(this).val();
            const config = window.PrintManagerConfig || {};

            $.get(config.routes.settingsShow, { branch_id: branchId })
                .done(function (response) {
                    if (!response.success || !response.data) {
                        return;
                    }

                    const setting = response.data;
                    $('#print_method').val(setting.print_method);
                    $('#paper_size').val(setting.paper_size);
                    $('#copies').val(setting.copies);
                    $('#cash_drawer').val(setting.cash_drawer);
                    $('#status').val(setting.status);
                    $('#character_encoding').val(setting.character_encoding);

                    if (setting.margins) {
                        $('#margin_top').val(setting.margins.top || 0);
                        $('#margin_right').val(setting.margins.right || 0);
                        $('#margin_bottom').val(setting.margins.bottom || 0);
                        $('#margin_left').val(setting.margins.left || 0);
                    }

                    PrintManagerPrinter.populatePrinterSelect('#default_printer', $('#default_printer option').map(function () {
                        return $(this).val();
                    }).get().filter(Boolean), setting.default_printer);
                });
        });

        $('#printSettingsForm').on('submit', function (event) {
            event.preventDefault();

            const $btn = $('#saveSettingsBtn');
            const config = window.PrintManagerConfig || {};

            $btn.prop('disabled', true);

            $.ajax({
                url: config.routes.settingsStore,
                method: 'POST',
                data: $(this).serialize()
            }).done(function (response) {
                if (typeof showSuccessNotification === 'function') {
                    showSuccessNotification(response.message || 'Settings saved.');
                }
            }).fail(function (xhr) {
                const message = xhr.responseJSON?.message || 'Unable to save print settings.';
                if (typeof showErrorNotification === 'function') {
                    showErrorNotification(message);
                } else {
                    alert(message);
                }
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

    });
})(window, jQuery);
