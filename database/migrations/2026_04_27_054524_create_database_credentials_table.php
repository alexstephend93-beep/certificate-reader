<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('database_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('connection_name', ['mysql', 'pgsql', 'sqlite'])->default('mysql');
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('database');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index('is_default');
            $table->index('connection_name');
            $table->index('is_active');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('database_credentials');
    }
};