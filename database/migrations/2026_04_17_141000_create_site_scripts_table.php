<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_scripts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('placement', 32)->index();
            $table->string('source_type', 32);
            $table->string('source_url', 2048)->nullable();
            $table->string('file_path')->nullable();
            $table->longText('inline_content')->nullable();
            $table->string('load_strategy', 32)->default('blocking');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('settings')) {
            return;
        }

        $legacyScripts = DB::table('settings')
            ->whereIn('key', ['head_scripts', 'body_scripts'])
            ->pluck('value', 'key');

        $now = now();
        $rows = [];

        $headScripts = trim((string) ($legacyScripts['head_scripts'] ?? ''));

        if ($headScripts !== '') {
            $rows[] = [
                'name' => 'Legacy Head Scripts',
                'placement' => 'head',
                'source_type' => 'inline',
                'source_url' => null,
                'file_path' => null,
                'inline_content' => $headScripts,
                'load_strategy' => 'blocking',
                'sort_order' => 0,
                'is_enabled' => true,
                'notes' => 'Migrated from the legacy General Settings head scripts field.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $bodyScripts = trim((string) ($legacyScripts['body_scripts'] ?? ''));

        if ($bodyScripts !== '') {
            $rows[] = [
                'name' => 'Legacy Body Scripts',
                'placement' => 'body_end',
                'source_type' => 'inline',
                'source_url' => null,
                'file_path' => null,
                'inline_content' => $bodyScripts,
                'load_strategy' => 'blocking',
                'sort_order' => 100,
                'is_enabled' => true,
                'notes' => 'Migrated from the legacy General Settings body scripts field.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('site_scripts')->insert($rows);

            DB::table('settings')
                ->whereIn('key', ['head_scripts', 'body_scripts'])
                ->update([
                    'value' => '',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_scripts');
    }
};
