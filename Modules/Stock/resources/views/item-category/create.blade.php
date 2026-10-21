@extends('backend.backend_layout')
@section('page-title', isset($itemCategory) ? __('Update') . ' ' . __('Item') . ' ' . __('Category') : __('Add') . ' ' . __('Item') . ' ' . __('Category'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($itemCategory) ? __('Update') . ' ' . __('Item') . ' ' . __('Category') : __('Add') . ' ' . __('Item') . ' ' . __('Category') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Item') . ' ' . __('Configuration'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($itemCategory) ? __('Update') . ' ' . __('Item') . ' ' . __('Category') : __('Add') . ' ' . __('Item') . ' ' . __('Category'),
                    'active' => true
                ]
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
            <form action="{{ isset($itemCategory) ? route('item-category.update', encrypt($itemCategory->id)) : route('item-category.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if(isset($itemCategory))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Category') }} {{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Category') }} {{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($itemCategory) ? $itemCategory->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea type="text" class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        >{{ old('description', isset($itemCategory) ? $itemCategory->description : '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="banner">{{ __('Category Banner') }} <span class="text-muted">({{ __('Optional') }})</span></label>
                                    <input type="file" class="form-control @error('banner') is-invalid @enderror" name="banner" id="banner" accept="image/*">
                                    @error('banner')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if(!empty($itemCategory?->banner))
                                        <div class="mt-2 d-flex align-items-start gap-3">
                                            <img src="{{ $itemCategory->banner_url }}" alt="{{ __('Category Banner') }}" style="max-height: 80px; border: 1px dashed #d1d0d4; border-radius: 5px; padding: 4px;">
                                            <label class="form-check mt-2">
                                                <input type="checkbox" class="form-check-input" name="remove_banner" value="1">
                                                <span class="form-check-label">{{ __('Remove Banner') }}</span>
                                            </label>
                                        </div>
                                    @endif
                                    <small class="text-muted d-block mt-1">{{ __('Shown on the ecommerce category page.') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($itemCategory) ? $itemCategory : '') !!}
                            </button>
                            <a href="{{ route('item-category.index') }}" class="btn btn-primary waves-effect waves-light">
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

@push('page-js')
@endpush