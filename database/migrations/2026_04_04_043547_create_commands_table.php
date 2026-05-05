<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->text('command');
            $table->text('description')->nullable();
            $table->text('alternate_commands')->nullable();
            $table->text('example_usage')->nullable();
            $table->text('notes')->nullable();

            // ✅ FIX: keep as text but use FULLTEXT instead of normal index
            $table->text('tags')->nullable();

            $table->string('os')->default('all');
            $table->string('danger_level')->default('low');
            $table->integer('usage_count')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->string('icon')->nullable();
            $table->string('command_type')->default('cli');
            $table->timestamps();

            // indexes
            $table->index('category');
            $table->index('sub_category');
            $table->index('name');

            // ✅ FIX: use FULLTEXT instead of index
            $table->fullText('tags');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commands');
    }
};