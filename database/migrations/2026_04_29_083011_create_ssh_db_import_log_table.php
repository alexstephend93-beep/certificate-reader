<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ssh_db_import_log', function (Blueprint $table) {
            $table->id();
            $table->string('ssh_host', 255);           // Host alias from ~/.ssh/config
            $table->string('domain', 255);             // Domain for which DB was imported
            $table->foreignId('database_credential_id')
                  ->nullable()
                  ->constrained('database_credentials')
                  ->onDelete('set null');
            $table->text('project_path')->nullable();   // Absolute path to Laravel project root
            $table->text('env_path')->nullable();       // Full path to .env file that was read
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamp('last_synced_at')->nullable();
            $table->enum('import_status', ['success', 'failed', 'pending'])->default('success');
            $table->text('error_message')->nullable();
            $table->text('env_vars_snapshot')->nullable(); // JSON snapshot of DB_* vars at import time
            
            // Unique constraint: one record per (ssh_host, domain) pair
            $table->unique(['ssh_host', 'domain']);
            
            // Indexes for queries
            $table->index('ssh_host');
            $table->index('imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssh_db_import_log');
    }
};
