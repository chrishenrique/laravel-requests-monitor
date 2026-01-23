<?php

return [
    'domain' => env('REQUESTS_MONITOR_DOMAIN', 'app'),
    'connection' => env('REQUESTS_MONITOR_CONNECTION', 'requests_monitor'),
    'retention_days' => (int) env('REQUESTS_MONITOR_RETENTION_DAYS', 90),
    'monitor_resolver' => \ChrisHenrique\RequestsMonitor\Monitoring\DefaultRequestsMonitor::class,

    'exclude' => [
        'urls' => [
            '/health',
            '/up',
            '/ping',
        ],

        // Route names
        'routes' => [
            'debugbar.*',
            'telescope.*',
            'horizon.*',
            'pulse.*',
            'livewire.*',
        ],

        // Regex patterns
        'patterns' => [
            '/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot)$/i',
            '/api/v1/auth/login',
        ],
    ],
];