<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_title', 255)->nullable()->after('type');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('og_image', 2048)->nullable()->after('meta_description');
            $table->timestamp('publish_at')->nullable()->after('og_image');
            $table->timestamp('unpublish_at')->nullable()->after('publish_at');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image', 'publish_at', 'unpublish_at']);
        });
    }
};
