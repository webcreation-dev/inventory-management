/**
 * Cascade subcategory options when category changes on item create/edit.
 */
(function ($) {
    "use strict";

    function loadSubCategories(categoryId, selectedId) {
        const $sub = $("#sub_category_id");
        if (!$sub.length) {
            return;
        }

        const url = $sub.data("by-category-url");
        const placeholder = $sub.data("placeholder") || "Select Sub Category";
        const keepOptions = $sub.find("option[value!='']").length > 0;
        const previousHtml = keepOptions ? $sub.html() : null;
        const previousVal = selectedId || $sub.attr("data-selected") || $sub.val() || "";

        $sub.empty().append(new Option(placeholder, "", false, false));

        if (!categoryId || !url) {
            $sub.val("").trigger("change");
            return;
        }

        $.getJSON(url, { category_id: categoryId })
            .done(function (res) {
                const results = res.results || [];
                results.forEach(function (row) {
                    $sub.append(new Option(row.text, row.id, false, false));
                });

                const selected = selectedId ? String(selectedId) : "";
                if (selected && $sub.find("option[value='" + selected.replace(/'/g, "\\'") + "']").length) {
                    $sub.val(selected);
                } else {
                    $sub.val("");
                }
                $sub.trigger("change.select2");
            })
            .fail(function () {
                // Keep blade-rendered options on edit when AJAX is denied (permissions).
                if (previousHtml) {
                    $sub.html(previousHtml);
                    if (previousVal) {
                        $sub.val(String(previousVal));
                    }
                    $sub.trigger("change.select2");
                    return;
                }
                $sub.val("").trigger("change");
            });
    }

    $(function () {
        const $category = $("#category_id");
        const $sub = $("#sub_category_id");
        if (!$category.length || !$sub.length) {
            return;
        }

        const initialSelected = $sub.attr("data-selected") || $sub.val() || "";
        let lastCategoryId = String($category.val() || "");

        $category.on("change", function () {
            const nextCategoryId = String($(this).val() || "");
            // Ignore re-select of the same category (e.g. select2 re-init after modal).
            if (nextCategoryId === lastCategoryId) {
                return;
            }
            lastCategoryId = nextCategoryId;
            loadSubCategories(nextCategoryId, null);
        });

        if (lastCategoryId) {
            const hasPreloaded = $sub.find("option[value!='']").length > 0 && initialSelected;
            if (hasPreloaded) {
                $sub.val(String(initialSelected)).trigger("change.select2");
            } else {
                loadSubCategories(lastCategoryId, initialSelected);
            }
        }
    });
})(jQuery);
