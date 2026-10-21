@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-slate-900">Product Comparison</h1>
            <p class="mt-1 text-sm text-slate-500">Compare up to 3 products side-by-side.</p>
            @if(!empty($compareNotice))
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {{ $compareNotice }}
                </div>
            @endif
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-44 px-4 py-3 text-left font-semibold text-slate-700">Feature</th>
                        @foreach($compareProducts as $product)
                            <th class="min-w-[220px] px-4 py-3 text-left font-semibold text-slate-700">{{ $product['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Image</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3">
                                <img src="{{ route('ecommerce.image', ['file' => $product['image']]) }}" alt="{{ $product['name'] }}" class="h-28 w-28 rounded-lg object-cover">
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Category</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3 text-slate-600">{{ $product['category'] }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Brand</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3 text-slate-600">{{ $product['brand'] }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Rating</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3 text-slate-600">★ {{ number_format($product['rating'], 1) }} ({{ $product['reviews'] }})</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Price</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3">
                                <p class="font-semibold text-brand-700">${{ number_format($product['price'], 2) }}</p>
                                <p class="text-xs text-slate-400 line-through">${{ number_format($product['old_price'], 2) }}</p>
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Specification</td>
                        @foreach($compareProducts as $product)
                            <td class="px-4 py-3 text-slate-600">{{ $product['spec'] }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
