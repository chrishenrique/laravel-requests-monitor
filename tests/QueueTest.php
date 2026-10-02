<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Jobs\StoreRequest;
use ChrisHenrique\RequestsMonitor\Monitoring\DefaultRequestsMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

class QueueTest extends TestCase
{
    public function test_job_is_dispatched_to_the_configured_queue(): void
    {
        config([
            'requests-monitor.queue.connection' => 'redis',
            'requests-monitor.queue.name'       => 'monitor',
        ]);

        Queue::fake();

        app(RequestsMonitor::class)->logManually(['method' => 'EVENT', 'action_name' => 'x']);

        Queue::assertPushedOn('monitor', StoreRequest::class, function (StoreRequest $job) {
            return $job->connection === 'redis'
                && $job->data['action_name'] === 'x'
                && $job->data['domain'] === 'tests';
        });
    }

    public function test_uploaded_files_are_stored_as_metadata_only(): void
    {
        Queue::fake();

        $request = Request::create('/upload', 'POST', ['title' => 'doc'], [], [
            'file' => UploadedFile::fake()->create('report.pdf', 10),
        ]);

        app(RequestsMonitor::class)->logFromRequest($request);

        Queue::assertPushed(StoreRequest::class, function (StoreRequest $job) {
            $input = $job->data['content']['input'];

            return $input['title'] === 'doc'
                && $input['file']['name'] === 'report.pdf'
                && $input['file']['size'] === 10240;
        });
    }

    public function test_monitor_resolver_can_be_replaced(): void
    {
        config(['requests-monitor.monitor_resolver' => CustomMonitor::class]);

        $this->assertInstanceOf(CustomMonitor::class, app(RequestsMonitor::class));
    }
}

class CustomMonitor extends DefaultRequestsMonitor
{
}
