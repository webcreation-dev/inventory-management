@extends('backend.backend_layout')
@section('page-title', $partner->exists ? __('Update') . ' ' . __('Delivery Partner') : __('Add') . ' ' . __('Delivery Partner'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $partner->exists ? __('Update') . ' ' . __('Delivery Partner') : __('Add') . ' ' . __('Delivery Partner') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.delivery-partners.index')],
                ['label' => $partner->exists ? __('Update') . ' ' . __('Delivery Partner') : __('Add') . ' ' . __('Delivery Partner'), 'active' => true],
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
            <form method="POST" novalidate
                  action="{{ $partner->exists ? route('ecommerce.admin.delivery-partners.update', $partner->id) : route('ecommerce.admin.delivery-partners.store') }}">
                @csrf
                @if($partner->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $partner->name) }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2 @error('status') is-invalid @enderror">
                                    <option value="Active" @selected(old('status', $partner->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $partner->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 mb-5">
                                <label class="form-label" for="description">{{ __('Description') }}</label>
                                <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $partner->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($partner->exists ? $partner : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.delivery-partners.index') }}" class="btn btn-primary waves-effect waves-light">
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
