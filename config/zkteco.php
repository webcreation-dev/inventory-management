<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Device network
    |--------------------------------------------------------------------------
    |
    | ZKTeco attendance devices usually listen on UDP port 4370.
    | The web server must reach this IP (same LAN or VPN). Enable the PHP
    | sockets extension (see jmrashed/zkteco README).
    |
    */
    'host' => env('ZKTECO_HOST'),

    'port' => (int) env('ZKTECO_PORT', 4370),

    /*
    |--------------------------------------------------------------------------
    | Sync behaviour
    |--------------------------------------------------------------------------
    |
    | Limit how many newest raw log rows to read per run (null = full log).
    | Lower values are faster on busy devices; use null for first import.
    |
    */
    'attendance_limit' => env('ZKTECO_ATTENDANCE_LIMIT') !== null && env('ZKTECO_ATTENDANCE_LIMIT') !== ''
        ? (int) env('ZKTECO_ATTENDANCE_LIMIT')
        : null,

];
