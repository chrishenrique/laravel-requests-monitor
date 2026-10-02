<?php

namespace ChrisHenrique\RequestsMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Artisan;

class RequestMonitor extends Model
{
    protected $connection;
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
        'execution_ms',
        'content',
        'created_at',
        'execution_ms',
    ];

    protected $casts = [
        'content' => 'array',
        'execution_ms' => 'float',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->connection = config('requests-monitor.connection', config('database.default'));
    }

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Query de registros expirados (mesmo critério do requests-monitor:prune).
     */
    public function prunable()
    {
        $days = (int) config('requests-monitor.prune_after_days', 90);
        $domain = config('requests-monitor.domain');

        return static::where('created_at', '<', now()->subDays($days))
            ->when($domain, function ($query, $domain) {
                $query->where('domain', $domain);
            });
    }

    /**
     * Remove registros antigos (funciona do Laravel 7 ao 13).
     */
    public static function pruneOld(): void
    {
        Artisan::call('requests-monitor:prune');
    }
}
