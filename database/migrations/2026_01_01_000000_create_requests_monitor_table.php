<?php
// database/migrations/create_requests_monitor_logs_table.php.stub

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('requests-monitor.connection'))
            ->createIfNotExists('requests_monitor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('domain');
                $table->string('method', 10);
                $table->string('requester_type')->nullable();
                $table->unsignedBigInteger('requester_id')->nullable();
                $table->string('url');
                $table->string('route_name')->nullable();
                $table->string('action_name')->nullable();
                $table->json('content');
                $table->timestamp('created_at')->useCurrent();
                
                // Índices para performance no prune e queries comuns
                $table->index(['domain', 'created_at']);
                $table->index('requester_type');
                $table->index('requester_id');
            });
    }

    public function down(): void
    {
        Schema::connection(config('requests-monitor.connection'))
            ->dropIfExists('requests_monitor');
    }
};
