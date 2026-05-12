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
            'defaultPresetId' => StampPreset::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('id'),
        ]);
    }

    public function upload(Request $request, ManualStampService $stampService)
    {
        $request->validate([
            'files'     => ['required', 'array', 'min:1', 'max:10'],
            'files.*'   => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'preset_id' => ['nullable', 'integer', 'exists:stamp_presets,id'],
        ]);

        // Build three independent preset payloads (one per output type)
        $masterPreset       = null;
        $controlledPreset   = null;
        $uncontrolledPreset = null;

        if ($request->filled('preset_id')) {
            /** @var StampPreset $presetModel */
            $presetModel = StampPreset::query()
                ->where('is_active', true)
                ->findOrFail((int) $request->input('preset_id'));

            $masterPreset = [
                'stamps' => $presetModel->master_stamps ?? [],
                'esign'  => $presetModel->esign,
            ];
            $controlledPreset = [
                'stamps' => $presetModel->controlled_stamps ?? [],
                'esign'  => $presetModel->esign,
            ];
            $uncontrolledPreset = [
                'stamps' => $presetModel->uncontrolled_stamps ?? [],
                'esign'  => $presetModel->esign,
            ];
        }

        $timestamp        = now()->format('Ymd_His');
        $jobId            = (string) Str::uuid();
        $workingDirectory = "manual-stamping/generated/{$timestamp}_{$jobId}";

        Storage::makeDirectory($workingDirectory);

        $uploadedFiles = $request->file('files');
        $usedBaseNames = [];
        $zipEntries    = [];

        try {
            foreach ($uploadedFiles as $uploadedFile) {
                $rawBase  = Str::slug(
                    pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)
                ) ?: 'manual';

                $baseName = $rawBase;
                $counter  = 1;
                while (in_array($baseName, $usedBaseNames)) {
                    $baseName = "{$rawBase}_{$counter}";
                    $counter++;
                }
                $usedBaseNames[] = $baseName;

                $docDir = "{$workingDirectory}/{$baseName}";
                Storage::makeDirectory($docDir);

                $storedRelPath = $uploadedFile->storeAs($docDir, 'source.pdf');
                if ($storedRelPath === false) {
                    throw new RuntimeException("Unable to store uploaded PDF: {$baseName}.");
                }

                $sourcePath       = Storage::path($storedRelPath);
                $masterPath       = Storage::path("{$docDir}/master.pdf");
                $controlledPath   = Storage::path("{$docDir}/controlled.pdf");
                $uncontrolledPath = Storage::path("{$docDir}/uncontrolled.pdf");

                $stampService->stampMasterCopy($sourcePath, $masterPath, $masterPreset);
                $stampService->stampControlledCopy($sourcePath, $controlledPath, $controlledPreset);
                $stampService->stampUncontrolledCopy($sourcePath, $uncontrolledPath, $uncontrolledPreset);

                $zipEntries[$masterPath]       = "{$baseName}/{$baseName}_MASTER.pdf";
                $zipEntries[$controlledPath]   = "{$baseName}/{$baseName}_CONTROLLED.pdf";
                $zipEntries[$uncontrolledPath] = "{$baseName}/{$baseName}_UNCONTROLLED.pdf";
            }

            $zipName = count($uploadedFiles) === 1
                ? "{$usedBaseNames[0]}_stamped_copies.zip"
                : 'stamped_copies.zip';

            $zipPath = Storage::path("{$workingDirectory}/{$zipName}");

            $this->createZipArchive($zipPath, $zipEntries);
        } catch (Throwable $exception) {
            Storage::deleteDirectory($workingDirectory);
            throw $exception;
        }

        app()->terminating(function () use ($workingDirectory) {
            Storage::deleteDirectory($workingDirectory);
        });

        return response()->download(
            $zipPath,
            $zipName,
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