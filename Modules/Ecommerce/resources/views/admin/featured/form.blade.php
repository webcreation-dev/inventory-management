@extends('backend.backend_layout')
@section('page-title', $row->exists ? __('Update') . ' ' . __('Featured Product') : __('Add') . ' ' . __('Featured Product'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $row->exists ? __('Update') . ' ' . __('Featured Product') : __('Add') . ' ' . __('Featured Product') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.featured.index')],
                ['label' => $row->exists ? __('Update') . ' ' . __('Featured Product') : __('Add') . ' ' . __('Featured Product'), 'active' => true],
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
            <form method="POST" action="{{ $row->exists ? route('ecommerce.admin.featured.update', $row->id) : route('ecommerce.admin.featured.store') }}">
                @csrf
                @if($row->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="item_id">{{ __('Product') }} {!! requiredField() !!}</label>
                                <select name="item_id" id="item_id" class="form-select select2 @error('item_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected((int) old('item_id', $row->item_id) === (int) $item->id)>
                                            {{ $item->name }}{{ $item->code ? ' ('.$item->code.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="sort_id">{{ __('Sort') }}</label>
                                <input type="number" name="sort_id" id="sort_id" min="0" class="form-control" value="{{ old('sort_id', $row->sort_id ?? 0) }}">
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2">
                                    <option value="Active" @selected(old('status', $row->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $row->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($row->exists ? $row : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.featured.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
