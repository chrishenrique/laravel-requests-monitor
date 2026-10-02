<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Monitoring\QueryWatcher;
use Illuminate\Support\Facades\DB;

class QueryWatcherTest extends TestCase
{
    public function test_groups_identical_queries_and_sums_time(): void
    {
        $watcher = new QueryWatcher();

        $watcher->record('select * from users where id = ?', 1.5);
        $watcher->record('select * from users where id = ?', 2.5);
        $watcher->record('select * from posts', null);

        $all = $watcher->all();

        $this->assertSame(2, $all['select * from users where id = ?']['count']);
        $this->assertEqualsWithDelta(4.0, $all['select * from users where id = ?']['total_time_ms'], 0.001);
        $this->assertSame(1, $all['select * from posts']['count']);
        $this->assertSame(3, $watcher->totalQueries());

        $watcher->reset();

        $this->assertSame([], $watcher->all());
        $this->assertSame(0, $watcher->totalQueries());
    }

    public function test_db_listener_feeds_the_singleton_watcher(): void
    {
        $watcher = $this->app->make(QueryWatcher::class);
        $watcher->reset();

        DB::select('select 1');
        DB::select('select 1');

        $this->assertSame($watcher, $this->app->make(QueryWatcher::class));
        $this->assertSame(2, $watcher->all()['select 1']['count']);
    }
}
