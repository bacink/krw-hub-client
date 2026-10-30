<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BKPSDM HUB Client
    |--------------------------------------------------------------------------
    |
    | `base_url` adalah alamat hub (tanpa trailing slash). `client_id` dan
    | `client_secret` diperoleh dari superadmin hub. `redirect_uri` hanya
    | diperlukan bila memakai delegasi login user (harus terdaftar di hub).
    |
    */

    'base_url' => env('KRW_HUB_BASE_URL', 'http://localhost:8000'),

    'client_id' => env('KRW_HUB_CLIENT_ID'),

    'client_secret' => env('KRW_HUB_CLIENT_SECRET'),

    'redirect_uri' => env('KRW_HUB_REDIRECT_URI'),

    'timeout' => (int) env('KRW_HUB_TIMEOUT', 30),

];
