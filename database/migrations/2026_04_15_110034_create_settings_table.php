<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed defaults
        $now = now();
        DB::table('settings')->insert([
            ['key' => 'site_name',         'value' => config('app.name', 'My CMS'), 'group' => 'site',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'site_tagline',       'value' => '',                           'group' => 'site',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'admin_email',        'value' => '',                           'group' => 'site',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'meta_title_format',  'value' => '%page_title% | %site_name%', 'group' => 'seo',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'meta_description',   'value' => '',                           'group' => 'seo',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'homepage_id',        'value' => null,                         'group' => 'reading', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
