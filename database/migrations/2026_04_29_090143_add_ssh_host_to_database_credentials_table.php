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
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->string('ssh_host', 255)->nullable()->after('notes');
            $table->index('ssh_host');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->dropColumn('ssh_host');
        });
    }
};
