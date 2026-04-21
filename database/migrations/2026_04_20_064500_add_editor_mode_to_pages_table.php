<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('editor_mode', 16)->default('builder')->after('type');
        });

        DB::table('pages')
            ->whereNotNull('content')
            ->whereRaw("TRIM(content) != ''")
            ->where(function ($query) {
                $query->whereNull('blocks')
                    ->orWhere('blocks', '[]');
            })
            ->update(['editor_mode' => 'content']);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });
    }
};
