<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
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
    }

    public function down()
    {
        Schema::dropIfExists('admin_dashboards');
    }
};