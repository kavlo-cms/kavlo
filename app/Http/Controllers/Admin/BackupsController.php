<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupExporter;
use App\Services\BackupRestorer;
use App\Services\DeploymentReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupsController extends Controller
{
    public function __construct(
        private readonly BackupExporter $exporter,
        private readonly BackupRestorer $restorer,
        private readonly DeploymentReadinessService $readiness,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Backups/Index', [
            'stats' => $this->exporter->stats(),
            'readiness' => $this->readiness->report(),
            'checkpoints' => $this->exporter->recentCheckpoints(),
        ]);
    }

    public function export(): BinaryFileResponse|RedirectResponse
    {
        try {
            $archive = $this->exporter->createArchive();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return response()->download($archive['path'], $archive['filename'])->deleteFileAfterSend(true);
    }

    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'archive' => 'required|file|mimetypes:application/zip,application/x-zip-compressed,application/octet-stream|max:102400',
            'confirmation' => 'required|string|in:RESTORE',
        ]);

        try {
            $result = $this->restorer->restore($validated['archive']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archive' => $exception->getMessage()]);
        }

        return to_route('admin.backups.index')->with(
            'success',
            sprintf(
                'Backup restored. %d tables and %d public files were replaced.',
                $result['tables'],
                $result['files'],
            )
        );
    }

    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'archive' => 'required|file|mimetypes:application/zip,application/x-zip-compressed,application/octet-stream|max:102400',
        ]);

        try {
            $inspection = $this->restorer->inspect($validated['archive']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($inspection);
    }

    public function storeCheckpoint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:80',
        ]);

        try {
            $archive = $this->exporter->createArchive([
                'persist' => true,
                'purpose' => 'deployment-checkpoint',
                'label' => $validated['label'],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'label' => $exception->getMessage(),
            ]);
        }

        $label = $archive['label'] ?? $archive['filename'];

        return to_route('admin.backups.index')
            ->with('success', "Rollback checkpoint [{$label}] created.");
    }

    public function downloadCheckpoint(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|string',
        ]);

        try {
            $path = $this->exporter->resolveCheckpointPath($validated['file']);
        } catch (RuntimeException $exception) {
            return to_route('admin.backups.index')->with('error', $exception->getMessage());
        }

        return response()->download($path, basename($path));
    }
}
