/**
 * Dashboard: Due customers & supplier payables (server-side DataTables).
 * Pattern aligned with public/backend_assets/js/pages_list_js/list_customer.js
 */
$(function () {
    'use strict';

    let dueCustomersTable;
    let supplierPayablesTable;

    const base_url = $('#base_url').val() || '';

    function dashboardDtExtraParams(d) {
        d.outlet_id = $('#outlet_id').val() || '';
    }

    const $dueTable = $('.datatables-dashboard-due-customers');
    if ($dueTable.length) {
        dueCustomersTable = new DataTable($dueTable, {
            processing: true,
            serverSide: true,
            ajax: {
                url: base_url + '/dashboard/due-customers-data',
                type: 'GET',
                data: function (d) {
                    dashboardDtExtraParams(d);
                },
                error: function () {
                    window.location.reload();
                }
            },
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'total_due_amount', className: 'text-end' }
            ],
            dom:
                '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
            lengthMenu: [
                [10, 25, 50],
                [10, 25, 50]
            ],
            pageLength: 10,
            order: [],
            ordering: false
        });
        window.dashboardDueCustomersTable = dueCustomersTable;
    }

    const $supplierTable = $('.datatables-dashboard-supplier-payables');
    if ($supplierTable.length) {
        supplierPayablesTable = new DataTable($supplierTable, {
            processing: true,
            serverSide: true,
            ajax: {
                url: base_url + '/dashboard/supplier-payables-data',
                type: 'GET',
                data: function (d) {
                    dashboardDtExtraParams(d);
                },
                error: function () {
                    window.location.reload();
                }
            },
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'total_payable_amount', className: 'text-end' }
            ],
            dom:
                '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
            lengthMenu: [
                [10, 25, 50],
                [10, 25, 50]
            ],
            pageLength: 10,
            order: [],
            ordering: false
        });
        window.dashboardSupplierPayablesTable = supplierPayablesTable;
    }

    window.reloadDashboardDueTables = function () {
        if (window.dashboardDueCustomersTable && typeof window.dashboardDueCustomersTable.ajax.reload === 'function') {
            window.dashboardDueCustomersTable.ajax.reload(null, false);
        }
        if (window.dashboardSupplierPayablesTable && typeof window.dashboardSupplierPayablesTable.ajax.reload === 'function') {
            window.dashboardSupplierPayablesTable.ajax.reload(null, false);
        }
    };
});
