<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stamp_presets', function (Blueprint $table) {
            // Add the three per-copy stamp columns
            $table->json('master_stamps')->nullable()->after('description');
            $table->json('controlled_stamps')->nullable()->after('master_stamps');
            $table->json('uncontrolled_stamps')->nullable()->after('controlled_stamps');

            // Migrate existing data: copy old `stamps` into all three new columns
            // (done below via raw update after schema change)
        });

        // Migrate existing rows: copy old stamps → all three new columns
        \DB::statement('UPDATE stamp_presets SET master_stamps = stamps, controlled_stamps = stamps, uncontrolled_stamps = stamps WHERE stamps IS NOT NULL');

        Schema::table('stamp_presets', function (Blueprint $table) {
            $table->dropColumn('stamps');
        });
    }

    public function down(): void
    {
        Schema::table('stamp_presets', function (Blueprint $table) {
            $table->json('stamps')->nullable()->after('description');
        });

        \DB::statement('UPDATE stamp_presets SET stamps = master_stamps WHERE master_stamps IS NOT NULL');

        Schema::table('stamp_presets', function (Blueprint $table) {
            $table->dropColumn(['master_stamps', 'controlled_stamps', 'uncontrolled_stamps']);
        });
    }
};