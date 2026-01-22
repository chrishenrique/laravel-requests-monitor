<?php

namespace ChrisHenrique\RequestsMonitor\Middlewares;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use Closure;
use Illuminate\Http\Request;

class RequestMonitorMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $monitor = app(RequestsMonitor::class);

        $response = $next($request);

        // Compatível com Livewire Navigate (headers específicos)
        $isLivewire = $request->header('X-Livewire') ||
                      str_contains($request->header('Accept', ''), 'livewire+json');

        // Log sempre após o next() para capturar tudo
        $monitor->logFromRequest($request);

        return $response;
    }
}
