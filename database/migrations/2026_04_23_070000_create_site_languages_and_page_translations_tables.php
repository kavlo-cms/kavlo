<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('title');
            $table->string('slug');
            $table->text('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->json('metadata')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('unpublish_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'is_published']);
        });

        $defaultLocale = Str::of(config('app.locale', 'en'))
            ->replace('_', '-')
            ->lower()
            ->value();

        DB::table('site_languages')->insert([
            'code' => $defaultLocale,
            'name' => strtoupper($defaultLocale),
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pages = DB::table('pages')->get();

        foreach ($pages as $page) {
            DB::table('page_translations')->insert([
                'page_id' => $page->id,
                'locale' => $defaultLocale,
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'is_published' => $page->is_published,
                'metadata' => $page->metadata,
                'blocks' => $page->blocks,
                'published_at' => $page->published_at,
                'meta_title' => $page->meta_title ?? null,
                'meta_description' => $page->meta_description ?? null,
                'og_image' => $page->og_image ?? null,
                'publish_at' => $page->publish_at ?? null,
                'unpublish_at' => $page->unpublish_at ?? null,
                'created_at' => $page->created_at ?? now(),
                'updated_at' => $page->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('site_languages');
    }
};
