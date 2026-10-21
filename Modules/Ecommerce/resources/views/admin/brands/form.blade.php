@extends('backend.backend_layout')
@section('page-title', $row->exists ? __('Update') . ' ' . __('Popular Brand') : __('Add') . ' ' . __('Popular Brand'))
@section('page-content')
@php $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class); @endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $row->exists ? __('Update') . ' ' . __('Popular Brand') : __('Add') . ' ' . __('Popular Brand') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.brands.index')],
                ['label' => $row->exists ? __('Update') . ' ' . __('Popular Brand') : __('Add') . ' ' . __('Popular Brand'), 'active' => true],
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
            <form method="POST" enctype="multipart/form-data" novalidate
                  action="{{ $row->exists ? route('ecommerce.admin.brands.update', $row->id) : route('ecommerce.admin.brands.store') }}">
                @csrf
                @if($row->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="brand_id">{{ __('Brand') }} {!! requiredField() !!}</label>
                                <select name="brand_id" id="brand_id" class="form-select select2 @error('brand_id') is-invalid @enderror">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected((int) old('brand_id', $row->brand_id) === (int) $brand->id)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="sort_id">{{ __('Sort') }}</label>
                                <input type="number" name="sort_id" id="sort_id" min="0" class="form-control" value="{{ old('sort_id', $row->sort_id ?? 0) }}">
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2 @error('status') is-invalid @enderror">
                                    <option value="Active" @selected(old('status', $row->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $row->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="image">{{ __('Brand Image') }} @unless($row->exists && $row->image){!! requiredField() !!}@endunless</label>
                                <input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 120px × 60px') }}</small>
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($row->image)
                                    <img src="{{ $media->url($row->image) }}" class="mt-2" style="max-height:60px;" alt="">
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($row->exists ? $row : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.brands.index') }}" class="btn btn-primary waves-effect waves-light">
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
