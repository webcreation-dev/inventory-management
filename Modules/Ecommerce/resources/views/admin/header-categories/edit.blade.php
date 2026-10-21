@extends('backend.backend_layout')
@section('page-title', __('Header Category'))
@push('page-css')
<style>
    .hc-sortable-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .hc-sortable-item {
        background: #fff;
        border: 1px solid #d9dee3;
        border-radius: 0.5rem;
        padding: 0.875rem 1rem;
        margin-bottom: 0.65rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: box-shadow 0.15s ease, border-color 0.15s ease, background-color 0.15s ease;
        user-select: none;
        -webkit-user-select: none;
        touch-action: none;
    }
    .hc-sortable-item:hover {
        border-color: #8592a3;
        box-shadow: 0 0.125rem 0.35rem rgba(67, 89, 113, 0.08);
    }
    .hc-sortable-item.sortable-chosen {
        border-color: #696cff;
        background: #f8f7ff;
    }
    .hc-sortable-item.sortable-ghost {
        opacity: 0.45;
        background: #f5f5f9;
        border-style: dashed;
    }
    .hc-sortable-item.sortable-drag {
        opacity: 1;
        box-shadow: 0 0.75rem 1.5rem rgba(67, 89, 113, 0.18);
        border-color: #696cff;
        background: #fff;
        cursor: grabbing;
    }
    .hc-sort-number {
        background: #696cff;
        color: #fff;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.8125rem;
        flex-shrink: 0;
    }
    .hc-drag-handle {
        cursor: grab;
        color: #8592a3;
        font-size: 1.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        flex-shrink: 0;
        transition: color 0.15s ease, background-color 0.15s ease;
    }
    .hc-drag-handle:hover {
        color: #696cff;
        background: #f0f0ff;
    }
    .hc-drag-handle:active,
    .hc-sortable-item.sortable-drag .hc-drag-handle {
        cursor: grabbing;
    }
    .hc-item-check {
        flex-shrink: 0;
        margin: 0;
    }
    .hc-item-body {
        flex: 1;
        min-width: 0;
    }
    .hc-item-name {
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        line-height: 1.3;
    }
    .hc-item-meta {
        font-size: 0.8125rem;
        color: #a1acb8;
        margin: 0.15rem 0 0;
    }
    .hc-sortable-hint {
        font-size: 0.875rem;
        color: #a1acb8;
        margin-bottom: 1rem;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Header Category') }}</h4>
            <p class="text-muted mb-0 mt-1">{{ __('Select categories to show in the ecommerce header menu. Drag to set the header order. Subcategories with products appear under each selected category.') }}</p>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.branding.edit')],
                ['label' => __('Header Category'), 'active' => true],
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <form method="POST" action="{{ route('ecommerce.admin.header-categories.update') }}" id="headerCategoryForm" novalidate>
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0">{{ __('Categories') }}</h5>
                            <p class="hc-sortable-hint mb-0 mt-1">{{ __('Drag using the handle to reorder. Order is saved when you click Save.') }}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="select_all_header_categories">{{ __('Select All') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clear_all_header_categories">{{ __('Clear All') }}</button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($categories->isEmpty())
                            <p class="text-muted mb-0">{{ __('No categories found. Create categories in Stock first.') }}</p>
                        @else
                            <ul id="headerCategorySortable" class="hc-sortable-list">
                                @foreach($categories as $category)
                                    @php
                                        $isChecked = old('category_ids') !== null
                                            ? in_array((string) $category->id, array_map('strval', (array) old('category_ids')), true)
                                            : (bool) $category->show_in_header;
                                    @endphp
                                    <li class="hc-sortable-item" data-id="{{ $category->id }}">
                                        <span class="hc-sort-number">{{ $loop->iteration }}</span>
                                        <span class="hc-drag-handle" title="{{ __('Drag to reorder') }}" aria-label="{{ __('Drag to reorder') }}">
                                            <i class="ti tabler-grip-vertical"></i>
                                        </span>
                                        <input
                                            type="checkbox"
                                            class="form-check-input hc-item-check header-category-checkbox"
                                            name="category_ids[]"
                                            value="{{ $category->id }}"
                                            id="header_category_{{ $category->id }}"
                                            {{ $isChecked ? 'checked' : '' }}
                                        >
                                        <div class="hc-item-body">
                                            <label class="hc-item-name" for="header_category_{{ $category->id }}">
                                                {{ $category->name }}
                                            </label>
                                            <p class="hc-item-meta">{{ __('Sub Categories') }}: {{ (int) $category->sub_categories_count }}</p>
                                        </div>
                                        <input type="hidden" name="sorted_ids[]" class="hc-sorted-id" value="{{ $category->id }}">
                                    </li>
                                @endforeach
                            </ul>
                            @error('category_ids')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                            @error('sorted_ids')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>
                    @if($categories->isNotEmpty())
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti tabler-device-floppy me-1"></i>
                                {{ __('Save') }}
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script src="{{ asset('public/backend_assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script>
(function () {
    'use strict';

    const list = document.getElementById('headerCategorySortable');
    const selectAll = document.getElementById('select_all_header_categories');
    const clearAll = document.getElementById('clear_all_header_categories');
    const boxes = () => document.querySelectorAll('.header-category-checkbox');

    function updateSortNumbers() {
        if (!list) return;
        list.querySelectorAll('.hc-sortable-item').forEach(function (item, index) {
            const badge = item.querySelector('.hc-sort-number');
            if (badge) badge.textContent = String(index + 1);
            const hidden = item.querySelector('.hc-sorted-id');
            if (hidden) hidden.value = item.getAttribute('data-id') || '';
        });
    }

    function initSortable() {
        if (!list || typeof Sortable === 'undefined') {
            if (list && typeof Sortable === 'undefined') {
                console.error('SortableJS library is not loaded');
            }
            return;
        }

        Sortable.create(list, {
            handle: '.hc-drag-handle',
            animation: 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: false,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            preventOnFilter: true,
            filter: 'input, label, a, button',
            draggable: '.hc-sortable-item',
            onEnd: function () {
                updateSortNumbers();
            },
        });

        updateSortNumbers();
    }

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            boxes().forEach(function (el) { el.checked = true; });
        });
    }
    if (clearAll) {
        clearAll.addEventListener('click', function () {
            boxes().forEach(function (el) { el.checked = false; });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(initSortable, 50);
        });
    } else {
        setTimeout(initSortable, 50);
    }
})();
</script>
@endpush
