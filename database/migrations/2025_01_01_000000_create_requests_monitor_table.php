<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsMonitorTable extends Migration
{
    /**
     * The database schema.
     *
     * @var \Illuminate\Database\Schema\Builder
     */
    protected $schema;

    /**
     * Create a new migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->schema = Schema::connection($this->getConnection());
    }

    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function getConnection()
    {
        return config('requests-monitor.connection');
    }

    public function up(): void
    {
        if (!$this->schema->hasTable('requests_monitor')) 
        {
            $this->schema->create('requests_monitor', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('domain');
                $table->string('method', 10)->default('GET');
                $table->string('requester_type')->nullable();
                $table->unsignedBigInteger('requester_id')->nullable();
                $table->string('url')->nullable();
                $table->string('route_name')->nullable();
                $table->string('action_name')->nullable();
                $table->json('content')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->index(['domain', 'created_at']);
                $table->index('requester_type');
                $table->index('requester_id');
            });
        }
    }

    public function down(): void
    {
        $this->schema->dropIfExists('requests_monitor');
    }
};
