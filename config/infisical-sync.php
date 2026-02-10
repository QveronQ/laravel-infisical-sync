<?php

return [
    // Infisical server URL (cloud or self-hosted)
    'url' => env('INFISICAL_URL', 'https://app.infisical.com'),

    // Universal Auth credentials
    'client_id' => env('INFISICAL_CLIENT_ID'),
    'client_secret' => env('INFISICAL_CLIENT_SECRET'),

    // Default project and environment
    'project_id' => env('INFISICAL_PROJECT_ID'),
    'environment' => env('INFISICAL_ENVIRONMENT', 'dev'),

    // Default secret path
    'secret_path' => env('INFISICAL_SECRET_PATH', '/'),

    // Keys excluded from sync (supports wildcards via fnmatch)
    'exclude_keys' => [
        'INFISICAL_*',
    ],

    // Path to the .env file to synchronize
    'env_file' => base_path('.env'),
];
