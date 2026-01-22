<?php

return [
    'domain' => env('REQUESTS_MONITOR_DOMAIN', 'app'),
    'connection' => env('REQUESTS_MONITOR_CONNECTION', 'default'),

    'retention_days' => (int) env('REQUESTS_MONITOR_RETENTION_DAYS', 90),

    'logger_resolver' => \ChrisHenrique\RequestsMonitor\Monitoring\DefaultRequestMonitor::class,
];