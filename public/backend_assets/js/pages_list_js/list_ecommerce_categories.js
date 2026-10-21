$(async function () {
  "use strict";
  let base_url = $('#base_url').val();
  let language_name = $('#language_name').val();
  let language_file = base_url + '/resources/lang/' + language_name + '.json';
  let language_key = {};

  try {
    language_key = await (await fetch(language_file)).json();
  } catch (err) {
    console.error('Failed to load language file:', err);
  }

  const route_path = route('ecommerce.admin.categories.index', {}, false, Ziggy);
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
        {
          data: 'image_url',
          orderable: false,
          searchable: false,
          render: function (data) {
            return data ? `<img src="${data}" alt="" style="height:40px;border-radius:6px;">` : '—';
          }
        },
        { data: 'category' },
        { data: 'title' },
        { data: 'sort_id' },
        {
          data: 'status',
          orderable: false,
          searchable: false,
          render: function (data) {
            const ok = data === 'Active';
            return `<span class="badge bg-label-${ok ? 'success' : 'secondary'}">${ok ? (language_key.Active || 'Active') : (language_key.Inactive || 'Inactive')}</span>`;
          }
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('ecommerce.admin.categories.edit', { category: data.actual_id }, false, Ziggy);
            const destroy_url = route('ecommerce.admin.categories.destroy', { category: data.actual_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action">
              <a href="${base_url + edit_url}" class="btn btn-sm btn-icon edit-record"><i class="ti tabler-edit"></i></a>
              <button type="button" data-id="${data.actual_id}" class="delete_data btn btn-sm btn-icon delete-record"><i class="ti tabler-trash delete"></i></button>
              <form id="deleteForm-${data.actual_id}" action="${base_url + destroy_url}" method="POST" style="display: none;">
                <input type="hidden" name="_token" value="${csrf}">
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
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Add Category'] || 'Add Category') + '</span>',
          className: 'btn create-new btn-primary',
          action: function () {
            window.location.href = base_url + route('ecommerce.admin.categories.create', {}, false, Ziggy);
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }
});
