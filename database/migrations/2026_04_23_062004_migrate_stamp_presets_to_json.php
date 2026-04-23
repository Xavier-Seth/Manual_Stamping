<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stamp_presets', function (Blueprint $table) {
            // New JSON columns
            $table->json('stamps')->nullable()->after('description');
            $table->json('esign')->nullable()->after('stamps');
        });

        // Migrate existing rows: pack old flat fields into the new JSON shape
        DB::table('stamp_presets')->lazyById()->each(function (object $row) {
            $stamps = [];

            // Only migrate if at least one old stamp field has data
            if (
                isset($row->stamp_x) ||
                isset($row->stamp_y) ||
                isset($row->stamp_width) ||
                isset($row->stamp_height)
            ) {
                $stamps[] = [
                    'label' => 'MASTER COPY',
                    'sub_label' => 'LNU',
                    'type' => 'red',
                    'x' => (float) ($row->stamp_x ?? 0),
                    'y' => (float) ($row->stamp_y ?? 0),
                    'width' => (float) ($row->stamp_width ?? 34),
                    'height' => (float) ($row->stamp_height ?? 16),
                    'page_rule' => $row->stamp_page_rule ?? 'all',
                    'page_number' => isset($row->stamp_page_number) ? (int) $row->stamp_page_number : null,
                ];
            }

            $esign = null;
            if (!empty($row->esign_enabled)) {
                $esign = [
                    'enabled' => true,
                    'x' => isset($row->esign_x) ? (float) $row->esign_x : null,
                    'y' => isset($row->esign_y) ? (float) $row->esign_y : null,
                    'width' => isset($row->esign_width) ? (float) $row->esign_width : 30,
                    'height' => isset($row->esign_height) ? (float) $row->esign_height : 10,
                    'page_rule' => $row->esign_page_rule ?? 'last',
                    'page_number' => isset($row->esign_page_number) ? (int) $row->esign_page_number : null,
                ];
            }

            DB::table('stamp_presets')
                ->where('id', $row->id)
                ->update([
                    'stamps' => json_encode($stamps),
                    'esign' => $esign !== null ? json_encode($esign) : null,
                ]);
        });

        Schema::table('stamp_presets', function (Blueprint $table) {
            // Drop old flat stamp columns
            $table->dropColumn([
                'stamp_enabled',
                'stamp_x',
                'stamp_y',
                'stamp_width',
                'stamp_height',
                'stamp_page_rule',
                'stamp_page_number',
                // Drop old flat esign columns
                'esign_enabled',
                'esign_x',
                'esign_y',
                'esign_width',
                'esign_height',
                'esign_page_rule',
                'esign_page_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('stamp_presets', function (Blueprint $table) {
            // Restore old flat columns
            $table->boolean('stamp_enabled')->default(true)->after('description');
            $table->decimal('stamp_x', 8, 2)->default(0)->after('stamp_enabled');
            $table->decimal('stamp_y', 8, 2)->default(0)->after('stamp_x');
            $table->decimal('stamp_width', 8, 2)->default(34)->after('stamp_y');
            $table->decimal('stamp_height', 8, 2)->default(16)->after('stamp_width');
            $table->string('stamp_page_rule', 20)->default('all')->after('stamp_height');
            $table->unsignedInteger('stamp_page_number')->nullable()->after('stamp_page_rule');

            $table->boolean('esign_enabled')->default(false)->after('stamp_page_number');
            $table->decimal('esign_x', 8, 2)->nullable()->after('esign_enabled');
            $table->decimal('esign_y', 8, 2)->nullable()->after('esign_x');
            $table->decimal('esign_width', 8, 2)->nullable()->after('esign_y');
            $table->decimal('esign_height', 8, 2)->nullable()->after('esign_width');
            $table->string('esign_page_rule', 20)->nullable()->after('esign_height');
            $table->unsignedInteger('esign_page_number')->nullable()->after('esign_page_rule');
        });

        // Best-effort reverse migration: unpack the first stamp entry back to flat fields
        DB::table('stamp_presets')->lazyById()->each(function (object $row) {
            $stamps = json_decode($row->stamps ?? '[]', true) ?? [];
            $esign = json_decode($row->esign ?? 'null', true);
            $first = $stamps[0] ?? [];

            DB::table('stamp_presets')
                ->where('id', $row->id)
                ->update([
                    'stamp_enabled' => count($stamps) > 0,
                    'stamp_x' => $first['x'] ?? 0,
                    'stamp_y' => $first['y'] ?? 0,
                    'stamp_width' => $first['width'] ?? 34,
                    'stamp_height' => $first['height'] ?? 16,
                    'stamp_page_rule' => $first['page_rule'] ?? 'all',
                    'stamp_page_number' => $first['page_number'] ?? null,

                    'esign_enabled' => !empty($esign['enabled']),
                    'esign_x' => $esign['x'] ?? null,
                    'esign_y' => $esign['y'] ?? null,
                    'esign_width' => $esign['width'] ?? null,
                    'esign_height' => $esign['height'] ?? null,
                    'esign_page_rule' => $esign['page_rule'] ?? null,
                    'esign_page_number' => $esign['page_number'] ?? null,
                ]);
        });

        Schema::table('stamp_presets', function (Blueprint $table) {
            $table->dropColumn(['stamps', 'esign']);
        });
    }
};