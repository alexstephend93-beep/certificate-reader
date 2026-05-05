<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->string('phpmyadmin_url')->nullable()->after('notes');
            $table->index('phpmyadmin_url');
        });
    }

    public function down()
    {
        Schema::table('database_credentials', function (Blueprint $table) {
            $table->dropColumn('phpmyadmin_url');
        });
    }
};