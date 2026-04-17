<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('description');
            $table->string('submission_action')->nullable()->after('blocks');
            $table->json('action_config')->nullable()->after('submission_action');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['blocks', 'submission_action', 'action_config']);
        });
    }
};
