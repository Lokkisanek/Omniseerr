<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_configs', function (Blueprint $table) {
            $table->id();
            $table->string('service')->unique(); // tmdb, radarr, sonarr, qbittorrent
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->json('defaults')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('ghost_media', function (Blueprint $table) {
            $table->id();
            $table->string('media_type'); // movie | series
            $table->unsignedBigInteger('tmdb_id');
            $table->unsignedBigInteger('external_arr_id');
            $table->timestamps();

            $table->unique(['media_type', 'tmdb_id']);
            $table->index(['media_type', 'external_arr_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghost_media');
        Schema::dropIfExists('service_configs');
    }
};
