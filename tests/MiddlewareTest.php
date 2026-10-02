<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor;
use ChrisHenrique\RequestsMonitor\Models\RequestMonitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('requests-monitor')->group(function () {
            Route::any('/monitored', function () {
                return 'ok';
            })->name('monitored');

            Route::get('/health', function () {
                return 'ok';
            });

            Route::get('/slow', function () {
                for ($i = 0; $i < 6; $i++) {
                    DB::select('select 1 where 1 = ?', [$i]);
                }
                usleep(20000);

                return 'slow';
            });
        });
    }

    public function test_request_is_logged_after_response(): void
    {
        $this->get('/monitored?foo=bar')->assertOk();

        $record = $this->lastRecord();

        $this->assertNotNull($record);
        $this->assertSame('tests', $record->domain);
        $this->assertSame('GET', $record->method);
        $this->assertSame('monitored', $record->route_name);
        $this->assertStringContainsString('/monitored?foo=bar', $record->url);
        $this->assertGreaterThan(0, $record->execution_ms);
        $this->assertSame(['foo' => 'bar'], $record->content['input']);
        $this->assertArrayNotHasKey('slow', $record->content);
    }

    public function test_sensitive_headers_and_input_are_masked(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'Cookie'        => 'session=abc',
            'X-Custom'      => 'visible',
        ])->post('/monitored', [
            'name'     => 'John',
            'password' => 'super-secret',
            'nested'   => ['token' => 'abc'],
        ])->assertOk();

        $content = $this->lastRecord()->content;

        $this->assertSame(['********'], $content['headers']['authorization']);
        $this->assertSame(['********'], $content['headers']['cookie']);
        $this->assertSame(['visible'], $content['headers']['x-custom']);
        $this->assertSame('John', $content['input']['name']);
        $this->assertSame('********', $content['input']['password']);
        $this->assertSame('********', $content['input']['nested']['token']);

        $raw = json_encode($content);
        $this->assertStringNotContainsString('secret-token', $raw);
        $this->assertStringNotContainsString('super-secret', $raw);
    }

    public function test_ignored_urls_are_not_logged(): void
    {
        $this->get('/health')->assertOk();

        $this->assertSame(0, RequestMonitor::query()->count());
    }

    public function test_ignored_headers_are_not_logged(): void
    {
        $this->withHeaders(['X-Livewire' => 'true'])->get('/monitored')->assertOk();

        $this->assertSame(0, RequestMonitor::query()->count());
    }

    public function test_ignored_methods_are_not_logged(): void
    {
        $this->call('OPTIONS', '/monitored');

        $this->assertSame(0, RequestMonitor::query()->count());
    }

    public function test_slow_request_runs_collectors_and_detects_n_plus_one(): void
    {
        config(['requests-monitor.slow_request.threshold_ms' => 1]);

        $this->get('/slow')->assertOk();

        $slow = $this->lastRecord()->content['slow'];

        $this->assertArrayHasKey('server', $slow);
        $this->assertArrayHasKey('process_memory_mb', $slow['server']);

        $this->assertTrue($slow['n_plus_1']['enabled']);
        $this->assertCount(1, $slow['n_plus_1']['suspects']);
        $this->assertSame(6, $slow['n_plus_1']['suspects'][0]['count']);

        $this->assertArrayNotHasKey('pg_activity', $slow);
        $this->assertArrayNotHasKey('apache', $slow);
    }

    public function test_slow_request_monitoring_can_be_disabled(): void
    {
        config([
            'requests-monitor.slow_request.enabled'      => false,
            'requests-monitor.slow_request.threshold_ms' => 1,
        ]);

        $this->get('/slow')->assertOk();

        $record = $this->lastRecord();

        $this->assertNotNull($record);
        $this->assertArrayNotHasKey('slow', $record->content);
    }

    public function test_monitor_failure_does_not_break_the_response(): void
    {
        $this->app->bind(RequestsMonitor::class, function () {
            return new class implements RequestsMonitor {
                public function logFromRequest(Request $request, ?Model $requester = null, array $context = []): void
                {
                    throw new \RuntimeException('monitor down');
                }

                public function logManually(array $attributes): void
                {
                }
            };
        });

        $this->get('/monitored')->assertOk()->assertSee('ok');
    }

    public function test_disabled_monitor_logs_nothing(): void
    {
        config(['requests-monitor.enabled' => false]);

        $this->get('/monitored')->assertOk();

        $this->assertSame(0, RequestMonitor::query()->count());
    }
}
