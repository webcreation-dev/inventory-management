@foreach($products as $product)
    @include('ecommerce::partials.product-card', [
        'product' => $product,
        'money' => $money,
        'productImage' => $productImage,
        'badge' => $badge ?? null,
        'badgeClass' => $badgeClass ?? 'ec-badge-sale',
        'wishlistIds' => $wishlistIds ?? [],
    ])
@endforeach
