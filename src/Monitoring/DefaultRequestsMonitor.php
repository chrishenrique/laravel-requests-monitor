<?php

namespace ChrisHenrique\RequestsMonitor\Monitoring;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DefaultRequestsMonitor implements RequestsMonitor
{
    public function shouldSkipRequest(\Illuminate\Http\Request $request): bool
    {
        $config = config('requests-monitor.exclude', []);

        if (in_array($request->path(), $config['urls'] ?? [])) {
            return true;
        }

        $routeName = optional($request->route())->getName();
        if ($routeName && $this->matchesAny($routeName, $config['routes'] ?? [])) {
            return true;
        }

        foreach (($config['patterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $request->fullUrl())) {
                return true;
            }
        }

        if ($request->method() === 'HEAD') {
            return true;
        }

        if (in_array($request->method(), $config['methods'] ?? [])) {
            return true;
        }

         if (str_contains($request->path(), 'favicon') || 
            preg_match('/\.(css|js|png|jpg|gif|svg|ico|woff)/i', $request->path())) {
            return true;
        }

        return false;
    }

    protected function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $value) || $pattern === $value) {
                return true;
            }
        }
        return false;
    }

    public function logFromRequest(Request $request, ?Model $requester = null): void
    {
        if ($this->shouldSkipRequest($request)) {
            return;
        }

        $user = $requester ?? $request->user();

        $route = $request->route();

        $data = [
            'domain'         => config('requests-monitor.domain'),
            'method'         => $request->method(),
            'requester_type' => $user ? get_class($user) : null,
            'requester_id'   => $user ? $user->getKey() : null,
            'url'            => $request->fullUrl(),
            'route_name'     => $route ? $route->getName() : null,
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
