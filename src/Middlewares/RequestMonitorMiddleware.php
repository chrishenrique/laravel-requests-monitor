<?php

namespace ChrisHenrique\RequestsMonitor\Middlewares;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Monitoring\QueryWatcher;
use ChrisHenrique\RequestsMonitor\Monitoring\SlowRequestCollectorManager;
use Closure;
use Illuminate\Http\Request;

class RequestMonitorMiddleware
{
    /**
     * Atributo da request onde o tempo de execução é guardado entre handle() e terminate().
     */
    public const EXECUTION_MS_ATTRIBUTE = 'requests_monitor.execution_ms';

    public function handle(Request $request, Closure $next)
    {
        // Zera o contador de queries no início (relevante em Octane/workers
        // longos; em Apache/mod_php cada request já começa limpa).
        app(QueryWatcher::class)->reset();

        $start = microtime(true);

        $response = $next($request);

        // Mede aqui (tempo até a resposta ficar pronta); o registro em si
        // acontece no terminate(), depois que a resposta foi enviada.
        $executionMs = defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000, 2)
            : round((microtime(true) - $start) * 1000, 2);

        $request->attributes->set(self::EXECUTION_MS_ATTRIBUTE, $executionMs);

        return $response;
    }

    /**
     * Executado após o envio da resposta (com PHP-FPM o cliente não espera
     * pelos collectors nem pelo dispatch do job).
     */
    public function terminate(Request $request, $response): void
    {
        if (! $request->attributes->has(self::EXECUTION_MS_ATTRIBUTE)) {
            return;
        }

        $executionMs = (float) $request->attributes->get(self::EXECUTION_MS_ATTRIBUTE);
        $request->attributes->remove(self::EXECUTION_MS_ATTRIBUTE);

        try {
            $context = ['execution_ms' => $executionMs];

            // Só obtém dados de lentidão quando a request ultrapassa o threshold.
            // Em requests aceitáveis nada é coletado nem descartado.
            $slow = app(SlowRequestCollectorManager::class);

            if ($slow->isEnabled() && $slow->exceedsThreshold($executionMs)) {
                $context['slow'] = $slow->collect($request, $executionMs);
            }

            app(RequestsMonitor::class)->logFromRequest($request, null, $context);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
