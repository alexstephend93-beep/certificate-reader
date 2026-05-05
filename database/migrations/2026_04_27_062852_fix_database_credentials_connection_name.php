<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->string('connection_name')->default('mysql')->change();
        });
        
        // Update existing null values
        DB::table('database_credentials')->whereNull('connection_name')->update(['connection_name' => 'mysql']);
    }

    public function down()
    {
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->string('connection_name')->nullable()->change();
        });
    }
};
