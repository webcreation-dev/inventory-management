@php
    /** @var \Illuminate\Support\Collection|array $items */
    $items = collect($items ?? []);
    $createUrl = $createUrl ?? '#';
    $createLabel = $createLabel ?? __('Add');
    $sortUrl = $sortUrl ?? '';
    $emptyMessage = $emptyMessage ?? __('No items found.');
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">{{ $listTitle ?? __('Items') }}</h5>
            <p class="ec-sortable-hint mt-1">{{ __('Drag using the handle to reorder. Click Save Order to apply.') }}</p>
        </div>
        <a href="{{ $createUrl }}" class="btn btn-primary">
            <i class="ti tabler-plus me-1"></i>{{ $createLabel }}
        </a>
    </div>
    <div class="card-body">
        @if($items->isEmpty())
            <p class="text-muted mb-0">{{ $emptyMessage }}</p>
        @else
            <ul id="ecommerceCmsSortable" class="ec-sortable-list" data-sort-url="{{ $sortUrl }}">
                @foreach($items as $item)
                    <li class="ec-sortable-item" data-id="{{ $item['id'] }}">
                        <span class="ec-sort-number">{{ $loop->iteration }}</span>
                        <span class="ec-drag-handle" title="{{ __('Drag to reorder') }}" aria-label="{{ __('Drag to reorder') }}">
                            <i class="ti tabler-grip-vertical"></i>
                        </span>
                        @if(!empty($item['image']))
                            <img src="{{ $item['image'] }}" alt="" class="ec-sortable-thumb">
                        @endif
                        <div class="ec-sortable-body">
                            <p class="ec-sortable-title">{{ $item['title'] }}</p>
                            @if(!empty($item['meta']))
                                <p class="ec-sortable-meta">{{ $item['meta'] }}</p>
                            @endif
                        </div>
                        @if(!empty($item['status']))
                            @php $ok = $item['status'] === 'Active'; @endphp
                            <span class="badge bg-label-{{ $ok ? 'success' : 'secondary' }}">
                                {{ $ok ? __('Active') : __('Inactive') }}
                            </span>
                        @endif
                        <div class="ec-sortable-actions datatable-action">
                            <a href="{{ $item['edit_url'] }}" class="btn btn-sm btn-icon edit-record" title="{{ __('Edit') }}">
                                <i class="ti tabler-edit"></i>
                            </a>
                            <button type="button" data-id="{{ $item['id'] }}" class="delete_data btn btn-sm btn-icon delete-record" title="{{ __('Delete') }}">
                                <i class="ti tabler-trash delete"></i>
                            </button>
                            <form id="deleteForm-{{ $item['id'] }}" action="{{ $item['destroy_url'] }}" method="POST" style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @if($items->isNotEmpty())
        <div class="card-footer">
            <button type="button" id="saveEcommerceSortOrder" class="btn btn-primary">
                <i class="ti tabler-device-floppy me-1"></i>{{ __('Save Order') }}
            </button>
        </div>
    @endif
</div>
