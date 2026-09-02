<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_servers', function (Blueprint $table) {
            $table->id();
            $table->string('host')->unique();
            $table->string('hostname')->nullable();
            $table->string('user')->nullable();
            $table->string('identity_file')->nullable();
            $table->integer('port')->default(22);
            $table->json('domains')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_servers');
    }
};

