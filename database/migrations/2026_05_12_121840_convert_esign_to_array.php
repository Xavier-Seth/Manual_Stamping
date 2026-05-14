<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $presets = DB::table('stamp_presets')->get(['id', 'esign']);

        foreach ($presets as $preset) {
            $raw = json_decode($preset->esign, true);

            if (is_array($raw) && !empty($raw['enabled'])) {
                $pageRule = $raw['page_rule'] ?? 'last';
                $newValue = json_encode([[
                    'x'           => isset($raw['x'])      ? (float) $raw['x']      : null,
                    'y'           => isset($raw['y'])      ? (float) $raw['y']      : null,
                    'width'       => isset($raw['width'])  ? (float) $raw['width']  : 30.0,
                    'height'      => isset($raw['height']) ? (float) $raw['height'] : 10.0,
                    'page_rule'   => $pageRule,
                    'page_number' => $pageRule === 'specific'
                                     ? ($raw['page_number'] ?? null) : null,
                ]]);
            } else {
                $newValue = json_encode([]);
            }

            DB::table('stamp_presets')
                ->where('id', $preset->id)
                ->update(['esign' => $newValue]);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op.
        // Cannot safely reverse a multi-esign array back to a single object.
    }
};
