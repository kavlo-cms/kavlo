<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // Modern Pro
            $table->string('slug')->unique(); // modern-pro
            $table->string('path');          // resources/themes/modern-pro
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable(); // Primary colors, fonts, etc.
            $table->string('version')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
