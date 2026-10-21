/**
 * Shared SortableJS list for Ecommerce CMS index pages.
 * Requires: #ecommerceCmsSortable, #saveEcommerceSortOrder, data-sort-url on the list.
 */
(function ($) {
    'use strict';

    function updateSortNumbers(list) {
        list.querySelectorAll('.ec-sortable-item').forEach(function (item, index) {
            var badge = item.querySelector('.ec-sort-number');
            if (badge) {
                badge.textContent = String(index + 1);
            }
        });
    }

    function initSortable() {
        var list = document.getElementById('ecommerceCmsSortable');
        var saveBtn = document.getElementById('saveEcommerceSortOrder');

        if (!list || typeof Sortable === 'undefined') {
            if (list && typeof Sortable === 'undefined') {
                console.error('SortableJS library is not loaded');
            }
            return;
        }

        Sortable.create(list, {
            handle: '.ec-drag-handle',
            animation: 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: false,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            preventOnFilter: true,
            filter: 'input, label, a, button, form',
            draggable: '.ec-sortable-item',
            onEnd: function () {
                updateSortNumbers(list);
            },
        });

        updateSortNumbers(list);

        if (!saveBtn) {
            return;
        }

        saveBtn.addEventListener('click', function () {
            var sortUrl = list.getAttribute('data-sort-url');
            if (!sortUrl) {
                return;
            }

            var sortedIds = [];
            list.querySelectorAll('.ec-sortable-item').forEach(function (item) {
                var id = item.getAttribute('data-id');
                if (id) {
                    sortedIds.push(id);
                }
            });

            if (!sortedIds.length) {
                if (typeof showErrorNotification === 'function') {
                    showErrorNotification('No items to sort');
                }
                return;
            }

            var $btn = $(saveBtn);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

            $.ajax({
                type: 'POST',
                url: sortUrl,
                data: {
                    sorted_ids: sortedIds,
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
                dataType: 'json',
            }).done(function (response) {
                if (response.status === 'success') {
                    if (typeof showSuccessNotification === 'function') {
                        showSuccessNotification(response.message || 'Order updated successfully');
                    }
                    updateSortNumbers(list);
                } else if (typeof showErrorNotification === 'function') {
                    showErrorNotification(response.message || 'Failed to update order');
                }
            }).fail(function (xhr) {
                var msg = 'An error occurred while updating order';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof showErrorNotification === 'function') {
                    showErrorNotification(msg);
                }
            }).always(function () {
                $btn.prop('disabled', false).html(originalHtml);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(initSortable, 50);
        });
    } else {
        setTimeout(initSortable, 50);
    }
})(jQuery);
