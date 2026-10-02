<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

class PruneCommandTest extends TestCase
{
    protected function createRecord(string $domain, int $daysAgo): void
    {
        RequestMonitor::query()->insert([
            'domain'     => $domain,
            'method'     => 'GET',
            'url'        => 'http://localhost/' . $daysAgo,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_prunes_only_old_records_of_the_current_domain_in_chunks(): void
    {
        config(['requests-monitor.prune_after_days' => 30]);

        for ($i = 0; $i < 5; $i++) {
            $this->createRecord('tests', 40 + $i); // antigos -> removidos
        }
        $this->createRecord('tests', 1);           // recente -> mantido
        $this->createRecord('other-app', 60);      // outro domínio -> mantido

        $this->artisan('requests-monitor:prune', ['--chunk' => 2])
            ->expectsOutput('Pruned 5 request monitor record(s).')
            ->assertExitCode(0);

        $this->assertSame(2, RequestMonitor::query()->count());
        $this->assertSame(1, RequestMonitor::query()->where('domain', 'tests')->count());
        $this->assertSame(1, RequestMonitor::query()->where('domain', 'other-app')->count());
    }

    public function test_days_option_overrides_config(): void
    {
        $this->createRecord('tests', 10);
        $this->createRecord('tests', 3);

        $this->artisan('requests-monitor:prune', ['--days' => 5])->assertExitCode(0);

        $this->assertSame(1, RequestMonitor::query()->count());
    }

    public function test_prune_old_helper_on_model(): void
    {
        $this->createRecord('tests', 200);
        $this->createRecord('tests', 1);

        RequestMonitor::pruneOld();

        $this->assertSame(1, RequestMonitor::query()->count());
        $this->assertSame(0, (new RequestMonitor())->prunable()->count());
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(function ($event) {
                return Str::contains((string) $event->command, 'requests-monitor:prune');
            });

        $this->assertCount(1, $events);
        $this->assertSame('0 2 * * *', $events->first()->expression);
    }
}
