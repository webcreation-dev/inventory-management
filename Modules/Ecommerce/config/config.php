<?php

return [
    'name' => 'Ecommerce',
    'upload_path' => 'public/uploads/ecommerce',
    'max_image_kb' => 2048,
    // Used when the storefront is opened without an admin company session.
    'default_company_id' => (int) env('ECOMMERCE_COMPANY_ID', 0),
    // Storefront path prefix: "web" when EC=true, otherwise "ecommerce".
    'storefront_prefix' => filter_var(env('EC', false), FILTER_VALIDATE_BOOLEAN) ? 'web' : 'ecommerce',
];
