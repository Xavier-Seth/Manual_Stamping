<?php

namespace App\Http\Controllers;

use App\Models\StampPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StampPresetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ManualStamping/Presets', [
            'presets' => StampPreset::query()
                ->orderBy('name')
                ->get()
                ->map(fn(StampPreset $preset) => [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'description' => $preset->description,
                    'master_stamps' => $preset->master_stamps ?? [],
                    'controlled_stamps' => $preset->controlled_stamps ?? [],
                    'uncontrolled_stamps' => $preset->uncontrolled_stamps ?? [],
                    'esign' => $preset->esign,
                    'is_active' => $preset->is_active,
                    'created_at' => optional($preset->created_at)?->toDateTimeString(),
                    'updated_at' => optional($preset->updated_at)?->toDateTimeString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePreset($request);

        StampPreset::create($data);

        return back()->with('success', 'Preset created successfully.');
    }

    public function update(Request $request, StampPreset $stampPreset): RedirectResponse
    {
        $data = $this->validatePreset($request);

        $stampPreset->update($data);

        return back()->with('success', 'Preset updated successfully.');
    }

    // -------------------------------------------------------------------------
    // Validation & normalisation
    // -------------------------------------------------------------------------

    private function validatePreset(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],

            // ── master stamps ──────────────────────────────────────────────
            'master_stamps' => ['required', 'array', 'min:1'],
            'master_stamps.*.label' => ['required', 'string', 'max:50'],
            'master_stamps.*.sub_label' => ['nullable', 'string', 'max:50'],
            'master_stamps.*.type' => ['required', 'in:red,black'],
            'master_stamps.*.x' => ['required', 'numeric', 'min:0'],
            'master_stamps.*.y' => ['required', 'numeric', 'min:0'],
            'master_stamps.*.width' => ['required', 'numeric', 'min:1'],
            'master_stamps.*.height' => ['required', 'numeric', 'min:1'],
            'master_stamps.*.page_rule' => ['required', 'in:all,first,last,specific'],
            'master_stamps.*.page_number' => ['nullable', 'integer', 'min:1'],

            // ── controlled stamps ──────────────────────────────────────────
            'controlled_stamps' => ['required', 'array', 'min:1'],
            'controlled_stamps.*.label' => ['required', 'string', 'max:50'],
            'controlled_stamps.*.sub_label' => ['nullable', 'string', 'max:50'],
            'controlled_stamps.*.type' => ['required', 'in:red,black'],
            'controlled_stamps.*.x' => ['required', 'numeric', 'min:0'],
            'controlled_stamps.*.y' => ['required', 'numeric', 'min:0'],
            'controlled_stamps.*.width' => ['required', 'numeric', 'min:1'],
            'controlled_stamps.*.height' => ['required', 'numeric', 'min:1'],
            'controlled_stamps.*.page_rule' => ['required', 'in:all,first,last,specific'],
            'controlled_stamps.*.page_number' => ['nullable', 'integer', 'min:1'],

            // ── uncontrolled stamps ────────────────────────────────────────
            'uncontrolled_stamps' => ['required', 'array', 'min:1'],
            'uncontrolled_stamps.*.label' => ['required', 'string', 'max:50'],
            'uncontrolled_stamps.*.sub_label' => ['nullable', 'string', 'max:50'],
            'uncontrolled_stamps.*.type' => ['required', 'in:red,black'],
            'uncontrolled_stamps.*.x' => ['required', 'numeric', 'min:0'],
            'uncontrolled_stamps.*.y' => ['required', 'numeric', 'min:0'],
            'uncontrolled_stamps.*.width' => ['required', 'numeric', 'min:1'],
            'uncontrolled_stamps.*.height' => ['required', 'numeric', 'min:1'],
            'uncontrolled_stamps.*.page_rule' => ['required', 'in:all,first,last,specific'],
            'uncontrolled_stamps.*.page_number' => ['nullable', 'integer', 'min:1'],

            // ── esign ──────────────────────────────────────────────────────
            'esign' => ['nullable', 'array'],
            'esign.enabled' => ['nullable', 'boolean'],
            'esign.x' => ['nullable', 'numeric', 'min:0'],
            'esign.y' => ['nullable', 'numeric', 'min:0'],
            'esign.width' => ['nullable', 'numeric', 'min:1'],
            'esign.height' => ['nullable', 'numeric', 'min:1'],
            'esign.page_rule' => ['nullable', 'in:first,last,specific'],
            'esign.page_number' => ['nullable', 'integer', 'min:1'],
        ]);

        // Normalise each stamp group
        foreach (['master_stamps', 'controlled_stamps', 'uncontrolled_stamps'] as $group) {
            $data[$group] = array_map(function (array $stamp): array {
                if (($stamp['page_rule'] ?? 'all') !== 'specific') {
                    $stamp['page_number'] = null;
                }
                return [
                    'label' => $stamp['label'],
                    'sub_label' => $stamp['sub_label'] ?? null,
                    'type' => $stamp['type'],
                    'x' => (float) $stamp['x'],
                    'y' => (float) $stamp['y'],
                    'width' => (float) $stamp['width'],
                    'height' => (float) $stamp['height'],
                    'page_rule' => $stamp['page_rule'],
                    'page_number' => $stamp['page_number'] ?? null,
                ];
            }, $data[$group]);
        }

        // Normalise esign
        $esign = $data['esign'] ?? null;

        if (empty($esign['enabled'])) {
            $data['esign'] = null;
        } else {
            if (($esign['page_rule'] ?? null) !== 'specific') {
                $esign['page_number'] = null;
            }
            $data['esign'] = [
                'enabled' => true,
                'x' => isset($esign['x']) ? (float) $esign['x'] : null,
                'y' => isset($esign['y']) ? (float) $esign['y'] : null,
                'width' => isset($esign['width']) ? (float) $esign['width'] : 30.0,
                'height' => isset($esign['height']) ? (float) $esign['height'] : 10.0,
                'page_rule' => $esign['page_rule'] ?? 'last',
                'page_number' => $esign['page_number'] ?? null,
            ];
        }

        return $data;
    }
}