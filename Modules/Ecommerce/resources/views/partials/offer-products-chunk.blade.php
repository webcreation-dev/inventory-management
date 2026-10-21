@foreach($products as $product)
    @include('ecommerce::partials.product-card', [
        'product' => $product,
        'money' => $money,
        'productImage' => $productImage,
        'badge' => __('Offer'),
        'badgeClass' => 'ec-badge-offer',
        'wishlistIds' => $wishlistIds ?? [],
    ])
@endforeach
