<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('media_type'); // movie | tv
            $table->unsignedBigInteger('tmdb_id');
            $table->unsignedBigInteger('tvdb_id')->nullable()->unique();
            $table->string('imdb_id')->nullable()->index();
            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->unsignedTinyInteger('status_4k')->default(1)->index();
            $table->unsignedInteger('external_service_id')->nullable();
            $table->unsignedInteger('external_service_id_4k')->nullable();
            $table->string('external_service_slug')->nullable();
            $table->string('external_service_slug_4k')->nullable();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('poster_path')->nullable();
            $table->timestamp('media_added_at')->nullable();
            $table->timestamps();

            $table->unique(['media_type', 'tmdb_id']);
            $table->index('tmdb_id');
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('app_settings');
    }
};
