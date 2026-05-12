<?php

namespace App\Http\Controllers;

use App\Models\StampPreset;
use App\Services\ManualStamping\ManualStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;
use ZipArchive;

class ManualStampController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ManualStamping/Index', [
            'presets' => StampPreset::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
        ]);
    }

    public function upload(Request $request, ManualStampService $stampService)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'preset_id' => ['nullable', 'integer', 'exists:stamp_presets,id'],
        ]);

        // Build three independent preset payloads (one per output type)
        $masterPreset = null;
        $controlledPreset = null;
        $uncontrolledPreset = null;

        if ($request->filled('preset_id')) {
            /** @var StampPreset $presetModel */
            $presetModel = StampPreset::query()
                ->where('is_active', true)
                ->findOrFail((int) $request->input('preset_id'));

            $masterPreset = [
                'stamps' => $presetModel->master_stamps ?? [],
                'esign' => $presetModel->esign,
            ];

            $controlledPreset = [
                'stamps' => $presetModel->controlled_stamps ?? [],
                'esign' => $presetModel->esign,
            ];

            $uncontrolledPreset = [
                'stamps' => $presetModel->uncontrolled_stamps ?? [],
                'esign' => $presetModel->esign,
            ];
        }

        $file = $request->file('file');

        $timestamp = now()->format('Ymd_His');
        $jobId = (string) Str::uuid();
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($baseName === '') {
            $baseName = 'manual';
        }

        $workingDirectory = "manual-stamping/generated/{$timestamp}_{$jobId}";

        Storage::makeDirectory($workingDirectory);

        $uploadedRelativePath = $file->storeAs($workingDirectory, 'source.pdf');

        if ($uploadedRelativePath === false) {
            throw new RuntimeException('Unable to store uploaded PDF.');
        }

        $inputPath = Storage::path($uploadedRelativePath);

        $masterRelativePath = "{$workingDirectory}/master_copy.pdf";
        $controlledRelativePath = "{$workingDirectory}/controlled_copy.pdf";
        $uncontrolledRelativePath = "{$workingDirectory}/uncontrolled_copy.pdf";
        $zipRelativePath = "{$workingDirectory}/{$baseName}_stamped_copies.zip";

        $masterPath = Storage::path($masterRelativePath);
        $controlledPath = Storage::path($controlledRelativePath);
        $uncontrolledPath = Storage::path($uncontrolledRelativePath);
        $zipPath = Storage::path($zipRelativePath);

        try {
            $stampService->stampMasterCopy($inputPath, $masterPath, $masterPreset);
            $stampService->stampControlledCopy($inputPath, $controlledPath, $controlledPreset);
            $stampService->stampUncontrolledCopy($inputPath, $uncontrolledPath, $uncontrolledPreset);

            $this->createZipArchive($zipPath, [
                $masterPath       => "{$baseName}_MASTER.pdf",
                $controlledPath   => "{$baseName}_CONTROLLED.pdf",
                $uncontrolledPath => "{$baseName}_UNCONTROLLED.pdf",
            ]);
        } catch (Throwable $exception) {
            Storage::deleteDirectory($workingDirectory);
            throw $exception;
        }

        app()->terminating(function () use ($workingDirectory) {
            Storage::deleteDirectory($workingDirectory);
        });

        return response()->download(
            $zipPath,
            "{$baseName}_stamped_copies.zip",
            ['Content-Type' => 'application/zip']
        );
    }

    private function createZipArchive(string $zipPath, array $files): void
    {
        $zip = new ZipArchive();
        $status = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($status !== true) {
            throw new RuntimeException('Unable to create output ZIP archive.');
        }

        foreach ($files as $filePath => $zipEntryName) {
            if (!$zip->addFile($filePath, $zipEntryName)) {
                $zip->close();
                throw new RuntimeException("Unable to add {$zipEntryName} to output ZIP archive.");
            }
        }

        $zip->close();
    }
}