<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Services\KavloStorage;
use App\Services\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function __construct(
        protected MediaUsageService $usage,
        protected KavloStorage $storage,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Media/Index', [
            'folders' => $this->getFolders(),
            'initialMedia' => $this->getMediaForFolder('uploads'),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $folder = $request->input('folder', 'uploads');
        $search = $request->input('search', '');
        $perPage = min((int) $request->input('per_page', 40), 100);

        $library = Library::singleton();
        $query = $library->media()->where('collection_name', $folder);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $media = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $media->getCollection()->map(fn (Media $m) => $this->formatMedia($m))->values(),
            'total' => $media->total(),
            'per_page' => $media->perPage(),
            'current_page' => $media->currentPage(),
            'last_page' => $media->lastPage(),
        ]);
    }

    public function folders(): JsonResponse
    {
        return response()->json($this->getFolders());
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:102400',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $this->isAllowedUpload($value)) {
                        $fail('The file field must be a supported media or document type.');
                    }
                },
            ],
            'folder' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        $folder = $request->input('folder', 'uploads');
        $library = Library::singleton();
        $original = $request->file('file')->getClientOriginalName();
        $name = pathinfo($original, PATHINFO_FILENAME);

        $media = $library
            ->addMedia($request->file('file'))
            ->usingName($name)
            ->toMediaCollection($folder, $this->storage->publicDiskName());

        return response()->json($this->formatMedia($media), 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'alt' => 'nullable|string|max:500',
            'folder' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        if (! empty($validated['name'])) {
            $media->name = $validated['name'];
        }

        if (array_key_exists('alt', $validated)) {
            $media->setCustomProperty('alt', $validated['alt'] ?? '');
        }

        if (! empty($validated['folder'])) {
            $media->collection_name = $validated['folder'];
        }

        $media->save();

        return response()->json($this->formatMedia($media));
    }

    public function destroy(Media $media): JsonResponse
    {
        $usage = $this->usage->forMedia($media);

        if ($usage['count'] > 0 && ! request()->boolean('force')) {
            return response()->json([
                'message' => 'This file is still referenced in the CMS.',
                'usage' => $usage,
            ], 422);
        }

        $media->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getFolders(): array
    {
        $library = Library::singleton();

        $collections = $library->media()
            ->select('collection_name')
            ->distinct()
            ->orderBy('collection_name')
            ->pluck('collection_name')
            ->toArray();

        if (empty($collections)) {
            $collections = ['uploads'];
        }

        return $collections;
    }

    private function getMediaForFolder(string $folder): array
    {
        $library = Library::singleton();

        return $library->media()
            ->where('collection_name', $folder)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Media $m) => $this->formatMedia($m))
            ->values()
            ->toArray();
    }

    private function formatMedia(Media $media): array
    {
        $usage = $this->usage->forMedia($media);
        $extension = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
        $kind = $this->mediaKind($media->mime_type ?? '', $extension);

        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'extension' => $extension,
            'kind' => $kind,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'download_url' => $media->getFullUrl(),
            'folder' => $media->collection_name,
            'alt' => $media->getCustomProperty('alt', ''),
            'created_at' => $media->created_at?->toISOString(),
            'usage_count' => $usage['count'],
            'usage' => $usage['references'],
        ];
    }

    private function allowedExtensions(): array
    {
        return [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'avif',
            'mp4',
            'mov',
            'webm',
            'avi',
            'mkv',
            'mp3',
            'm4a',
            'wav',
            'ogg',
            'pdf',
            'txt',
            'csv',
            'json',
            'zip',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
        ];
    }

    private function allowedClientMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/avif',
            'video/mp4',
            'video/quicktime',
            'video/webm',
            'video/x-msvideo',
            'video/x-matroska',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/webm',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/json',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
    }

    private function isAllowedUpload(UploadedFile $file): bool
    {
        $originalExtension = strtolower($file->getClientOriginalExtension());
        $guessedExtension = strtolower($file->guessExtension() ?? '');
        $clientMimeType = strtolower($file->getClientMimeType() ?? '');
        $guessedMimeType = strtolower($file->getMimeType() ?? '');

        if (in_array($originalExtension, $this->allowedExtensions(), true)) {
            return true;
        }

        if ($guessedExtension !== '' && in_array($guessedExtension, $this->allowedExtensions(), true)) {
            return true;
        }

        if (in_array($clientMimeType, $this->allowedClientMimeTypes(), true)) {
            return true;
        }

        return $guessedMimeType !== '' && in_array($guessedMimeType, $this->allowedClientMimeTypes(), true);
    }

    private function mediaKind(string $mimeType, string $extension): string
    {
        if ($extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'json'], true)) {
            return 'document';
        }

        if (in_array($extension, ['zip'], true)) {
            return 'archive';
        }

        if (Str::startsWith($mimeType, 'image/')) {
            return 'image';
        }

        if (Str::startsWith($mimeType, 'video/')) {
            return 'video';
        }

        if (Str::startsWith($mimeType, 'audio/')) {
            return 'audio';
        }

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        if (Str::contains($mimeType, 'zip')) {
            return 'archive';
        }

        return 'file';
    }
}
