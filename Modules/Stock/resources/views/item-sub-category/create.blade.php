@extends('backend.backend_layout')
@section('page-title', isset($itemSubCategory) ? __('Update') . ' ' . __('Sub Category') : __('Add') . ' ' . __('Sub Category'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($itemSubCategory) ? __('Update') . ' ' . __('Sub Category') : __('Add') . ' ' . __('Sub Category') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Item') . ' ' . __('Configuration'), 'link' => '#'],
                ['label' => isset($itemSubCategory) ? __('Update') . ' ' . __('Sub Category') : __('Add') . ' ' . __('Sub Category'), 'active' => true],
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
            <form action="{{ isset($itemSubCategory) ? route('item-sub-category.update', encrypt($itemSubCategory->id)) : route('item-sub-category.store') }}" method="POST">
                @csrf
                @if(isset($itemSubCategory))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="category_id">{{ __('Category') }} {!! requiredField() !!}</label>
                                <select name="category_id" id="category_id" class="form-select select2 @error('category_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected((int) old('category_id', $itemSubCategory->category_id ?? 0) === (int) $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="name">{{ __('Sub Category') }} {{ __('Name') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       name="name" id="name" placeholder="{{ __('Sub Category') }} {{ __('Name') }}"
                                       value="{{ old('name', $itemSubCategory->name ?? '') }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="description">{{ __('Description') }}</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          name="description" id="description"
                                          placeholder="{{ __('Description') }}">{{ old('description', $itemSubCategory->description ?? '') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($itemSubCategory) ? $itemSubCategory : '') !!}
                            </button>
                            <a href="{{ route('item-sub-category.index') }}" class="btn btn-primary waves-effect waves-light">
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
