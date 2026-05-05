<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Drop tables if they exist (in correct order)
        Schema::dropIfExists('admin_credentials');
        Schema::dropIfExists('admin_dashboards');
        
        // Create admin_dashboards table
        Schema::create('admin_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('integration_name')->nullable();
            $table->string('url');
            $table->string('icon')->default('box');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
        });
        
        // Create admin_credentials table
        Schema::create('admin_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('admin_dashboards')->onDelete('cascade');
            $table->string('email');
            $table->string('username')->nullable();
            $table->text('password');
            $table->string('role')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_credentials');
        Schema::dropIfExists('admin_dashboards');
    }
};