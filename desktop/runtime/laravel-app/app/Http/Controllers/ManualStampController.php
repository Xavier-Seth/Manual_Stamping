<?php

namespace App\Http\Controllers;

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
        return Inertia::render('ManualStamping/Index');
    }

    public function upload(Request $request, ManualStampService $stampService)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

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
            $stampService->stampMasterCopy($inputPath, $masterPath);
            $stampService->stampControlledCopy($inputPath, $controlledPath);
            $stampService->stampUncontrolledCopy($inputPath, $uncontrolledPath);

            $this->createZipArchive($zipPath, [
                $masterPath => 'master_copy.pdf',
                $controlledPath => 'controlled_copy.pdf',
                $uncontrolledPath => 'uncontrolled_copy.pdf',
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
            if (! $zip->addFile($filePath, $zipEntryName)) {
                $zip->close();

                throw new RuntimeException("Unable to add {$zipEntryName} to output ZIP archive.");
            }
        }

        $zip->close();
    }
}
