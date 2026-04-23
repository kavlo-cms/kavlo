<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'site_name')
            ->where('value', 'Laravel')
            ->update([
                'value' => 'Kavlo',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'site_name')
            ->where('value', 'Kavlo')
            ->update([
                'value' => 'Laravel',
                'updated_at' => now(),
            ]);
    }
};
