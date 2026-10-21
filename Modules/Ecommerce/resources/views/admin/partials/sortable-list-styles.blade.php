<style>
    .ec-sortable-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .ec-sortable-item {
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
    .ec-sortable-item:hover {
        border-color: #8592a3;
        box-shadow: 0 0.125rem 0.35rem rgba(67, 89, 113, 0.08);
    }
    .ec-sortable-item.sortable-chosen {
        border-color: #696cff;
        background: #f8f7ff;
    }
    .ec-sortable-item.sortable-ghost {
        opacity: 0.45;
        background: #f5f5f9;
        border-style: dashed;
    }
    .ec-sortable-item.sortable-drag {
        opacity: 1;
        box-shadow: 0 0.75rem 1.5rem rgba(67, 89, 113, 0.18);
        border-color: #696cff;
        background: #fff;
        cursor: grabbing;
    }
    .ec-sort-number {
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
    .ec-drag-handle {
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
    .ec-drag-handle:hover {
        color: #696cff;
        background: #f0f0ff;
    }
    .ec-drag-handle:active,
    .ec-sortable-item.sortable-drag .ec-drag-handle {
        cursor: grabbing;
    }
    .ec-sortable-thumb {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 0.375rem;
        border: 1px solid #e7e7e8;
        flex-shrink: 0;
        background: #f5f5f9;
    }
    .ec-sortable-body {
        flex: 1;
        min-width: 0;
    }
    .ec-sortable-title {
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        line-height: 1.3;
    }
    .ec-sortable-meta {
        font-size: 0.8125rem;
        color: #a1acb8;
        margin: 0.15rem 0 0;
    }
    .ec-sortable-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        flex-shrink: 0;
    }
    .ec-sortable-hint {
        font-size: 0.875rem;
        color: #a1acb8;
        margin-bottom: 0;
    }
</style>
