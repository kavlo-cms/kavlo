<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
            $table->timestamp('expires_at')->nullable()->after('last_used_ip');
            $table->index(['revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['revoked_at', 'expires_at']);
            $table->dropColumn(['last_used_ip', 'expires_at']);
        });
    }
};
