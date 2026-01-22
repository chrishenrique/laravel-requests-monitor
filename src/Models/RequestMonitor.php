<?php

namespace ChrisHenrique\RequestsMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Prunable;

class RequestMonitor extends Model
{
    use Prunable;

    protected $connection = 'requests_monitor';
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

    public function prunable()
    {
        $days = config('requests-monitor.retention_days', 90);
        $domain = config('requests-monitor.domain');

        return static::where('created_at', '<', now()->subDays($days))
            ->when($domain, function ($query, $domain) {
                $query->where('domain', $domain);
            });
    }
}
