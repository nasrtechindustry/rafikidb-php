<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Project id
    |--------------------------------------------------------------------------
    |
    | Your RafikiDB project id. Override with RAFIKIDB_PROJECT_ID in .env.
    |
    */
    'project_id' => env('RAFIKIDB_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Your project API key. Override with RAFIKIDB_API_KEY in .env.
    | Keep it server-side.
    |
    */
    'api_key' => env('RAFIKIDB_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | API base URL. Override with RAFIKIDB_URL in .env.
    |
    */
    'base_url' => env('RAFIKIDB_URL', 'http://localhost:8080/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Auth guard
    |--------------------------------------------------------------------------
    |
    | Set to true to register the 'rafikidb' auth guard so auth()->user()
    | resolves the project user stored in the session.
    |
    */
    'auth_guard' => env('RAFIKIDB_AUTH_GUARD', true),
];