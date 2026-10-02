<?php

namespace ChrisHenrique\RequestsMonitor\Tests;

use ChrisHenrique\RequestsMonitor\Contracts\SlowRequestCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ApacheCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\Concerns\ReadsFileTail;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PgStatActivityCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\PostgresSlowQueriesCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\Collectors\ServerMetricsCollector;
use ChrisHenrique\RequestsMonitor\Monitoring\SlowRequestCollectorManager;
use Illuminate\Http\Request;

class CollectorsTest extends TestCase
{
    /** @var array<int, string> */
    protected $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    protected function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rm-test-');
        file_put_contents($path, $contents);

        return $this->tempFiles[] = $path;
    }

    public function test_threshold_rules(): void
    {
        $manager = new SlowRequestCollectorManager();

        config(['requests-monitor.slow_request.threshold_ms' => 500]);
        $this->assertFalse($manager->exceedsThreshold(499.9));
        $this->assertTrue($manager->exceedsThreshold(500));

        // threshold 0 desliga a detecção
        config(['requests-monitor.slow_request.threshold_ms' => 0]);
        $this->assertFalse($manager->exceedsThreshold(99999));
    }

    public function test_manager_accepts_short_format_skips_disabled_and_isolates_failures(): void
    {
        config(['requests-monitor.slow_request.collectors' => [
            'fake'     => FakeCollector::class,                                   // formato curto
            'off'      => ['class' => FakeCollector::class, 'enabled' => false],  // desligado
            'broken'   => ['class' => BrokenCollector::class],                    // lança exceção
            'invalid'  => ['class' => \stdClass::class],                          // não implementa o contrato
            'no_class' => ['enabled' => true],                                    // sem classe
            FakeCollector::class,                                                  // chave numérica
        ]]);

        $data = (new SlowRequestCollectorManager())->collect(Request::create('/x'), 1234.5);

        $this->assertSame(['fake', 'FakeCollector'], array_keys($data));
        $this->assertSame(['execution_ms' => 1234.5, 'threshold_ms' => 100000.0], $data['fake']['context']);
    }

    public function test_server_metrics_collector(): void
    {
        $data = (new ServerMetricsCollector())->handle(Request::create('/'), []);

        $this->assertArrayHasKey('process_memory_mb', $data);
        $this->assertArrayHasKey('process_memory_peak_mb', $data);
        $this->assertArrayHasKey('cpu_count', $data);
    }

    public function test_postgres_collectors_report_unsupported_driver(): void
    {
        $request = Request::create('/');

        $activity = (new PgStatActivityCollector())->handle($request, []);
        $slowQueries = (new PostgresSlowQueriesCollector())->handle($request, []);

        $this->assertFalse($activity['supported']);
        $this->assertSame('sqlite', $activity['driver']);
        $this->assertFalse($slowQueries['supported']);
    }

    public function test_apache_collector_without_status_url_reads_logs(): void
    {
        $log = $this->tempFile("line 1\nline 2\nline 3\n");

        config(['requests-monitor.slow_request.apache' => [
            'status_url' => null,
            'logs'       => ['error' => $log, 'missing' => '/does/not/exist.log'],
            'log_lines'  => 2,
        ]]);

        $data = (new ApacheCollector())->handle(Request::create('/'), []);

        $this->assertSame(['enabled' => false], $data['mod_status']);
        $this->assertSame(['line 2', 'line 3'], $data['logs']['error']);
        $this->assertSame([], $data['logs']['missing']);
    }

    public function test_file_tail_reads_only_the_last_lines_of_large_files(): void
    {
        $lines = [];
        for ($i = 1; $i <= 2000; $i++) {
            $lines[] = "log line number {$i}";
        }

        $path = $this->tempFile(implode("\r\n", $lines) . "\n");
        $reader = new TailReader();

        $this->assertSame(['log line number 1998', 'log line number 1999', 'log line number 2000'], $reader->read($path, 3));
        $this->assertSame([], $reader->read($path, 0));
        $this->assertSame([], $reader->read('/does/not/exist', 5));
        $this->assertCount(2000, $reader->read($path, 5000));
    }
}

class FakeCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        return ['context' => $context];
    }
}

class BrokenCollector implements SlowRequestCollector
{
    public function handle(Request $request, array $context): array
    {
        throw new \RuntimeException('collector failed');
    }
}

class TailReader
{
    use ReadsFileTail;

    public function read(string $path, int $lines): array
    {
        return $this->tail($path, $lines);
    }
}
