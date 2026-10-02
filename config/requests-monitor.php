<?php

return [
    'domain' => env('REQUESTS_MONITOR_DOMAIN', 'app'),
    'connection' => env('REQUESTS_MONITOR_CONNECTION', 'requests_monitor'),
    'prune_after_days' => 90,
    // Registros apagados por lote no requests-monitor:prune (evita locks longos).
    'prune_chunk_size' => 1000,
    'monitor_resolver' => \ChrisHenrique\RequestsMonitor\Monitoring\DefaultRequestsMonitor::class,

    'enabled' => env('REQUESTS_MONITOR_ENABLED', true),

    /*
     * Monitoramento de requisições lentas.
     *
     * Quando o tempo de execução ultrapassa 'threshold_ms', os 'collectors'
     * são executados para obter informações adicionais sobre a lentidão.
     * Cada collector deve implementar:
     *   ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector
     * e os dados retornados ficam disponíveis em content['slow'][<chave>].
     */
    'slow_request' => [
        'enabled' => env('REQUESTS_MONITOR_SLOW_ENABLED', false),

        // Tempo aceitável (ms). Acima disso a requisição é considerada lenta
        // e TODOS os collectors habilitados são executados.
        'threshold_ms' => env('REQUESTS_MONITOR_SLOW_THRESHOLD_MS', 1000),

        // Opções do PgStatActivityCollector.
        'pg_activity' => [
            // Conexão consultada (null = conexão padrão da aplicação).
            'connection'  => env('REQUESTS_MONITOR_PG_ACTIVITY_CONNECTION'),
            // Ignora conexões ociosas (state = 'idle').
            'only_active' => true,
            // Máximo de linhas retornadas (0 = sem limite).
            'limit'       => 50,
        ],

        // Opções do PostgresSlowQueriesCollector (queries que atrapalham).
        'pg_slow_queries' => [
            'connection' => env('REQUESTS_MONITOR_PG_ACTIVITY_CONNECTION'),
            // Top N do pg_stat_statements por tempo total.
            'limit'      => 20,
            // Caminho do log do Postgres (log_min_duration_statement).
            // Ex: /var/log/postgresql/postgresql-16-main.log
            'log_path'   => env('REQUESTS_MONITOR_PG_LOG_PATH'),
            // Quantas linhas de "duration:" exibir.
            'log_lines'  => 30,
        ],

        // Opções do ApacheCollector (mod_status + logs).
        'apache' => [
            // URL do mod_status em formato máquina (?auto). Habilite o
            // mod_status no Apache e libere o acesso a partir do servidor.
            'status_url' => env('REQUESTS_MONITOR_APACHE_STATUS_URL', 'http://127.0.0.1/server-status?auto'),
            'timeout'    => 2,
            // Logs do Apache para tail (nome => caminho).
            'logs' => [
                // 'error'  => '/var/log/apache2/error.log',
                // 'access' => '/var/log/apache2/access.log',
            ],
            'log_lines' => 20,
        ],

        // Listener leve para detectar N+1 (só conta repetições do mesmo SQL).
        // Desligue para zero overhead por query.
        'query_watcher' => [
            'enabled' => env('REQUESTS_MONITOR_QUERY_WATCHER', true),
        ],

        // A partir de quantas execuções a MESMA query vira suspeita de N+1.
        'n_plus_one' => [
            'threshold' => 5,
        ],

        /*
         * Collectors: chave => configuração. Cada collector pode ser ligado/
         * desligado de forma independente:
         *   'class'   => classe do collector (obrigatório)
         *   'enabled' => liga/desliga este collector (default: true)
         *
         * Para criar o seu, implemente:
         *   ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector
         * Os collectors só são executados quando a request é lenta.
         */
        'collectors' => [
            'server' => [
                'class'   => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ServerMetricsCollector::class,
                'enabled' => false,
            ],
            'pg_activity' => [
                'class'   => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PgStatActivityCollector::class,
                'enabled' => false,
            ],
            'pg_slow_queries' => [
                'class'   => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PostgresSlowQueriesCollector::class,
                'enabled' => false,
            ],
            'apache' => [
                'class'   => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ApacheCollector::class,
                'enabled' => false,
            ],
            'n_plus_1' => [
                'class'   => \ChrisHenrique\RequestsMonitor\Monitoring\Collectors\NPlusOneCollector::class,
                'enabled' => false,
            ],
            // 'custom' => [
            //     'class'   => \App\Monitoring\Collectors\MeuCollector::class,
            //     'enabled' => true,
            // ],
        ],
    ],

    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'sync'),
        'name' => env('REQUESTS_MONITOR_QUEUE', 'default'),
    ],

    // Ex: 'password', 'password_confirmation', 'credit_card'
    'mask_fields' => [
        'password',
        'password_confirmation',
        'token',
        // Headers sensíveis (comparação sem diferenciar maiúsculas/minúsculas)
        'Authorization',
        'Cookie',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
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
            \Illuminate\Http\UploadedFile::class,
        ],
    ],
];