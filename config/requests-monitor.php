<?php

return [
    'domain' => env('REQUESTS_MONITOR_DOMAIN', 'app'),
    'connection' => env('REQUESTS_MONITOR_CONNECTION', 'requests_monitor'),
    'prune_after_days' => 90,
    'monitor_resolver' => \ChrisHenrique\RequestsMonitor\Monitoring\DefaultRequestsMonitor::class,

    'enabled' => env('REQUESTS_MONITOR_ENABLED', true),

    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'sync'),
        'name' => env('REQUESTS_MONITOR_QUEUE', 'default'),
    ],

    // Ex: 'password', 'password_confirmation', 'credit_card'
    'mask_fields' => [
        'password',
        'password_confirmation',
        'token',
        'Authorization', // Headers sensíveis
    ],

    'ignore' => [
        'urls' => [
            '/health',
            '/up',
            '/ping',
            '/livewire/update',
        ],

        'paths' => [
            'nova-api*',
            'horizon*',
            'telescope*',
            'admin/health-check',
        ],

        // Route names
        'routes' => [
            'debugbar.*',
            'telescope.*',
            'horizon.*',
            'pulse.*',
            'livewire.*',
        ],

        'headers' => [
            'X-Livewire',
            'X-Livewire-Navigate',
        ],

        // Regex patterns
        'patterns' => [
            '/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot)$/i',
        ],

        'methods' => ['OPTIONS', 'HEAD'],

        'input_types' => [
            Illuminate\Http\UploadedFile::class,
        ],
    ],
];