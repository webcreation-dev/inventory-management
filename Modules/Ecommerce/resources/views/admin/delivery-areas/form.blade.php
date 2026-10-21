@extends('backend.backend_layout')
@section('page-title', $area->exists ? __('Update') . ' ' . __('Delivery Area') : __('Add') . ' ' . __('Delivery Area'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $area->exists ? __('Update') . ' ' . __('Delivery Area') : __('Add') . ' ' . __('Delivery Area') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.delivery-areas.index')],
                ['label' => $area->exists ? __('Update') . ' ' . __('Delivery Area') : __('Add') . ' ' . __('Delivery Area'), 'active' => true],
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
                  action="{{ $area->exists ? route('ecommerce.admin.delivery-areas.update', $area->id) : route('ecommerce.admin.delivery-areas.store') }}">
                @csrf
                @if($area->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $area->name) }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $area->amount ?? 0) }}">
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2 @error('status') is-invalid @enderror">
                                    <option value="Active" @selected(old('status', $area->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $area->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 mb-5">
                                <label class="form-label" for="description">{{ __('Description') }}</label>
                                <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $area->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($area->exists ? $area : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.delivery-areas.index') }}" class="btn btn-primary waves-effect waves-light">
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
