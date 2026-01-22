<?php

namespace ChrisHenrique\RequestsMonitor\Jobs;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $model = new RequestMonitor();
        $model->setConnection(config('requests-monitor.connection'));
        $model->fill($this->data);
        $model->save();
    }
}
