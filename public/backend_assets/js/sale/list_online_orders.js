$(async function () {
  "use strict";

  let base_url = $('#base_url').val();
  let language_name = $('#language_name').val();
  let language_file = base_url + '/resources/lang/' + language_name + '.json';
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

  const statusBadge = function (status) {
    const map = {
      Pending: 'bg-label-warning',
      Confirmed: 'bg-label-primary',
      Rejected: 'bg-label-danger',
      'On The Way': 'bg-label-info',
      Delivered: 'bg-label-success',
      Cancelled: 'bg-label-secondary',
      Returned: 'bg-label-secondary',
      Unpaid: 'bg-label-danger',
      Partial: 'bg-label-warning',
      Paid: 'bg-label-success',
      Refunded: 'bg-label-secondary',
    };
    const cls = map[status] || 'bg-label-secondary';
    return `<span class="badge ${cls}">${status || '-'}</span>`;
  };

  const route_path = route('online-order.index', {}, false, Ziggy);
  const dt_basic_table = $('.datatables-basic');

  if (dt_basic_table.length) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        error: function () {
          window.location.reload();
        }
      },
      columns: [
        { data: 'id' },
        { data: 'sale_no' },
        {
          data: 'customer_name',
          render: function (data, type, row) {
            const phone = row.customer_phone && row.customer_phone !== '-'
              ? `<br><small class="text-muted">${row.customer_phone}</small>`
              : '';
            return `${data || '-'}${phone}`;
          }
        },
        { data: 'sale_date' },
        { data: 'grand_total' },
        {
          data: 'order_status',
          render: function (data) {
            return statusBadge(data);
          }
        },
        {
          data: 'payment_status',
          render: function (data) {
            return statusBadge(data);
          }
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const show_url = route('online-order.show', { id: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('online-order.destroy', { id: data.encrypted_id }, false, Ziggy);
            const print_url = route('sale.print-invoice', { sale: data.encrypted_id }, false, Ziggy);

            return `<div class="d-inline-flex datatable-action">
              <a href="${base_url + show_url}" class="btn btn-sm btn-icon" title="${language_key.Details || 'Details'}">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="${base_url + print_url}" target="_blank" class="btn btn-sm btn-icon" title="${language_key.Print || 'Print Invoice'}">
                <i class="ti tabler-printer"></i>
              </a>
              <button type="button" data-id="${data.actual_id}" class="delete_data btn btn-sm btn-icon delete-record" title="${language_key.Delete || 'Delete'}">
                <i class="ti tabler-trash delete"></i>
              </button>
              <form id="deleteForm-${data.actual_id}" action="${base_url + destroy_url}" method="POST" style="display: none;">
                <input type="hidden" name="_token" value="${csrf}">
                <input type="hidden" name="_method" value="DELETE">
              </form>
            </div>`;
          }
        }
      ],
      order: [],
      dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
        '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
        '<"row"<"col-sm-12"tr>>' +
        '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-label-primary dropdown-toggle me-2 waves-effect waves-light',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
          buttons: [
            { extend: 'print', text: '<i class="ti tabler-printer me-1"></i>Print', className: 'dropdown-item' },
            { extend: 'csv', text: '<i class="ti tabler-file-text me-1"></i>Csv', className: 'dropdown-item' },
            { extend: 'excel', text: '<i class="ti tabler-file-spreadsheet me-1"></i>Excel', className: 'dropdown-item' },
            { extend: 'pdf', text: '<i class="ti tabler-file-description me-1"></i>Pdf', className: 'dropdown-item' },
            { extend: 'copy', text: '<i class="ti tabler-copy me-1"></i>Copy', className: 'dropdown-item' }
          ]
        }
      ],
      language: {
        emptyTable: language_key['No data available in table'] || 'No online orders found'
      }
    });

    $('div.head-label').html('<h5 class="card-title mb-0">' + (language_key['Online Order'] || 'Online Order') + '</h5>');
  }
});
