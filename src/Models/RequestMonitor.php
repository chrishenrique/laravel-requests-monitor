<?php

namespace ChrisHenrique\RequestsMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RequestMonitor extends Model
{
    protected $connection = config('requests-monitor.connection');
    protected $table = 'requests_monitor';
    public $timestamps = false;

    protected $fillable = [
        'domain',
        'method',
        'requester_type',
        'requester_id',
        'url',
        'route_name',
        'action_name',
        'content',
        'created_at',
    ];

    protected $casts = [
        'content' => 'array',
        'created_at' => 'datetime',
    ];

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Laravel 8+: Prunable trait
     * Laravel 7: Manual implementation
     */
    public function prunable()
    {
        // Laravel 8+ com trait
        if (trait_exists(\Illuminate\Database\Eloquent\Prunable::class)) {
            return $this->performPruneQuery();
        }

        // Laravel 7 fallback
        return $this->performLaravel7Prune();
    }

    /**
     * Query comum para prune (usada por ambos)
     */
    protected function performPruneQuery()
    {
        $days = config('requests-monitor.retention_days', 90);
        $domain = config('requests-monitor.domain');

        return static::where('created_at', '<', now()->subDays($days))
            ->when($domain, function ($query, $domain) {
                $query->where('domain', $domain);
            });
    }

    /**
     * Laravel 7: Método manual (sem trait)
     */
    protected function performLaravel7Prune()
    {
        $query = $this->performPruneQuery();
        
        if (app()->runningInConsole() && $this->option('force')) {
            return $query->delete();
        }

        $this->warn("Laravel 7: Use 'php artisan requests-monitor:prune' instead of model:prune");
        return $query;
    }

    /**
     * Laravel 7: Método público para compatibilidade artisan model:prune-only
     */
    public static function pruneOld()
    {
        if (! trait_exists(\Illuminate\Database\Eloquent\Prunable::class)) {
            Artisan::call('requests-monitor:prune', ['--force' => true]);
            return;
        }

        // Laravel 8+: Normal prune
        static::prune();
    }
}
