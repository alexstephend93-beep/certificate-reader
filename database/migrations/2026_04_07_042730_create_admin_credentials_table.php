<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('admin_dashboards')->onDelete('cascade');
            $table->string('email');
            $table->string('username')->nullable();
            $table->text('password'); // Encrypted
            $table->string('role')->nullable(); // Admin, Editor, Viewer, etc.
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
    }
};