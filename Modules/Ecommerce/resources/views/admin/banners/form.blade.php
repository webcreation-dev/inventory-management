@extends('backend.backend_layout')
@section('page-title', $banner->exists ? __('Update') . ' ' . __('Banner') : __('Add') . ' ' . __('Banner'))
@section('page-content')
@php $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class); @endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $banner->exists ? __('Update') . ' ' . __('Banner') : __('Add') . ' ' . __('Banner') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.banners.index')],
                ['label' => $banner->exists ? __('Update') . ' ' . __('Banner') : __('Add') . ' ' . __('Banner'), 'active' => true],
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
                  action="{{ $banner->exists ? route('ecommerce.admin.banners.update', $banner->id) : route('ecommerce.admin.banners.store') }}">
                @csrf
                @if($banner->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="image">{{ __('Image') }} @unless($banner->exists){!! requiredField() !!}@endunless</label>
                                <input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 1690px × 600px') }}</small>
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($banner->image)
                                    <img src="{{ $media->url($banner->image) }}" class="mt-2" style="max-height:80px;border-radius:8px;" alt="">
                                @endif
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="link">{{ __('Link') }}</label>
                                <input type="text" name="link" id="link" class="form-control @error('link') is-invalid @enderror" value="{{ old('link', $banner->link) }}" placeholder="https://">
                                @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="sort_id">{{ __('Sort') }}</label>
                                <input type="number" name="sort_id" id="sort_id" min="0" class="form-control" value="{{ old('sort_id', $banner->sort_id ?? 0) }}">
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2 @error('status') is-invalid @enderror">
                                    <option value="Active" @selected(old('status', $banner->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $banner->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($banner->exists ? $banner : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.banners.index') }}" class="btn btn-primary waves-effect waves-light">
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
