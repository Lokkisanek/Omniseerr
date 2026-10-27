<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('permissions')->default(0)->after('password');
            $table->string('user_type')->default('local')->after('permissions'); // local | plex | jellyfin
            $table->string('avatar')->nullable()->after('user_type');
            $table->string('plex_id')->nullable()->unique()->after('avatar');
            $table->string('jellyfin_id')->nullable()->unique()->after('plex_id');
            $table->timestamp('last_login_at')->nullable()->after('jellyfin_id');
            $table->boolean('is_active')->default(true)->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'permissions',
                'user_type',
                'avatar',
                'plex_id',
                'jellyfin_id',
                'last_login_at',
                'is_active',
            ]);
        });
    }
};
