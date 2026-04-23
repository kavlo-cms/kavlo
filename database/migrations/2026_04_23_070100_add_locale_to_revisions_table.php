<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->string('locale', 16)->nullable()->after('page_id');
            $table->index(['page_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropIndex(['page_id', 'locale']);
            $table->dropColumn('locale');
        });
    }
};
