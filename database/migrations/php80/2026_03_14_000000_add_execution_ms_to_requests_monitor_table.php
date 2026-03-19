<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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
        $this->schema->table('requests_monitor', function (Blueprint $table) {
            $table->decimal('execution_ms', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        $this->schema->table('requests_monitor', function (Blueprint $table) {
            $table->dropColumn('execution_ms');
        });
    }
};
