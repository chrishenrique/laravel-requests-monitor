<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DefaultRequestsMonitor implements RequestsMonitor
{
    public function logFromRequest(Request $request, ?Model $requester = null): void
    {
        $user = $requester ?? $request->user();

        $route = $request->route();
        $routeName = $route?->getName();

        $data = [
            'domain'         => config('requests-monitor.domain'),
            'method'         => $request->method(),
            'requester_type' => $user ? get_class($user) : null,
            'requester_id'   => $user?->getKey(),
            'url'            => $request->fullUrl(),
            'route_name'     => $routeName,
            'action_name'    => null,
            'content'        => [
                'input'    => $request->all(),
                'headers'  => $request->headers->all(),
                'ip'       => $request->ip(),
            ],
            'created_at'     => now(),
        ];

        StoreRequest::dispatch($data);
    }

    public function logManually(array $attributes): void
    {
        $data = array_merge([
            'domain'       => config('requests-monitor.domain'),
            'created_at'   => now(),
        ], $attributes);

        StoreRequest::dispatch($data);
    }
}
