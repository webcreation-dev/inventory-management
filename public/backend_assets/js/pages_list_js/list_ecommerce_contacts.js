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

  const route_path = route('ecommerce.admin.contacts.index', {}, false, Ziggy);
  const dt_basic_table = $('.datatables-ecommerce-contacts');

  if (dt_basic_table.length) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        error: function () {
          window.location.reload();
        }
      },
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'email' },
        { data: 'subject' },
        { data: 'message_preview', orderable: false },
        { data: 'received' },
        { data: 'status_badge', orderable: false, searchable: false },
        { data: 'actions', orderable: false, searchable: false }
      ],
      columnDefs: [
        {
          targets: [2, 4, 6, 7],
          createdCell: function (td, cellData) {
            td.innerHTML = cellData;
          }
        }
      ],
      dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
        '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
        '<"row"<"col-sm-12"tr>>' +
        '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }

  const replyModal = document.getElementById('replyContactModal');
  const replyForm = document.getElementById('replyContactForm');
  if (replyModal && replyForm) {
    replyModal.addEventListener('show.bs.modal', function (e) {
      const btn = e.relatedTarget;
      if (!btn || !btn.getAttribute) return;
      replyForm.setAttribute('action', btn.getAttribute('data-reply-url') || '');
      replyForm.querySelector('#reply_subject').value = btn.getAttribute('data-subject-default') || '';
      replyForm.querySelector('#reply_message').value = '';
      const toEl = document.getElementById('replyContactToEmail');
      if (toEl) {
        toEl.textContent = (btn.getAttribute('data-contact-name') || '') +
          ' <' + (btn.getAttribute('data-contact-email') || '') + '>';
      }
    });
  }
});
