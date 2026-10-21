<?php

return [
    'name' => 'PrintManager',

    /*
    |--------------------------------------------------------------------------
    | Print environment
    |--------------------------------------------------------------------------
    |
    | development - QZ Tray runs with default trust prompts (no server signing).
    | production  - Requires real certificate + private key files and SHA512 signing.
    |
    */
    'env' => env('PRINT_ENV', 'development'),

    'methods' => [
        'browser' => 'Browser Print',
        'qz_tray' => 'QZ Tray',
        'local_agent' => 'Local Print Agent',
    ],

    'default_method' => env('PRINT_DEFAULT_METHOD', 'qz_tray'),

    'paper_sizes' => [
        '58mm' => '58mm (Thermal)',
        '80mm' => '80mm (Thermal)',
        'a4' => 'A4',
        'letter' => 'Letter',
    ],

    'default_paper_size' => '80mm',
    'default_copies' => 1,
    'default_character_encoding' => 'UTF-8',

    'default_margins' => [
        'top' => 0,
        'right' => 0,
        'bottom' => 0,
        'left' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | QZ Tray 2.x security (official signing workflow)
    |--------------------------------------------------------------------------
    |
    | Generate keys once (see storage/app/qz/README.md):
    |   openssl genrsa -out storage/app/qz/private-key.pem 2048
    |   openssl req -new -x509 -key storage/app/qz/private-key.pem \
    |     -out storage/app/qz/digital-certificate.txt -days 3650 -subj "/CN=Your Company"
    |
    | Or use QZ Tray > Advanced > Site Manager > Create New (demo keys for local dev).
    |
    | Official docs: https://qz.io/docs/signing
    |
    */
    'qz' => [
        'certificate_path' => env('QZ_CERTIFICATE_PATH', storage_path('app/qz/digital-certificate.txt')),
        'private_key_path' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/qz/private-key.pem')),
        'signature_algorithm' => env('QZ_SIGNATURE_ALGORITHM', 'SHA512'),
        'websocket_port' => (int) env('QZ_TRAY_WEBSOCKET_PORT', 8181),
        'secure_websocket_port' => (int) env('QZ_TRAY_SECURE_WEBSOCKET_PORT', 8182),
        'reconnect_interval_ms' => (int) env('QZ_TRAY_RECONNECT_INTERVAL_MS', 3000),
        'connection_timeout_ms' => (int) env('QZ_TRAY_CONNECTION_TIMEOUT_MS', 10000),
        'host' => env('QZ_TRAY_HOST', 'localhost'),
    ],

    'receipt' => [
        'footer' => env('QZ_TRAY_INVOICE_FOOTER', 'Thank you for shopping with us!'),
        'characters_per_line' => 42,
    ],

    'logging' => [
        'enabled' => env('PRINT_LOG_ENABLED', true),
        'channel' => env('PRINT_LOG_CHANNEL', 'stack'),
    ],
];
