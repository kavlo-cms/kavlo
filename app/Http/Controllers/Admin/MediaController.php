<?php

namespace App\Http\Controllers\Admin;

use App\Models\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function index(): \Inertia\Response
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
            'file'   => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,svg,pdf,mp4,mov',
            'folder' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
        ]);

        $folder = $request->input('folder', 'uploads');
        $library = Library::singleton();
        $original = $request->file('file')->getClientOriginalName();
        $name = pathinfo($original, PATHINFO_FILENAME);

        $media = $library
            ->addMedia($request->file('file'))
            ->usingName($name)
            ->toMediaCollection($folder, 'public');

        return response()->json($this->formatMedia($media), 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'alt'  => 'nullable|string|max:500',
        ]);

        if (! empty($validated['name'])) {
            $media->name = $validated['name'];
        }

        if (array_key_exists('alt', $validated)) {
            $media->setCustomProperty('alt', $validated['alt'] ?? '');
        }

        $media->save();

        return response()->json($this->formatMedia($media));
    }

    public function destroy(Media $media): JsonResponse
    {
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
        return [
            'id'         => $media->id,
            'name'       => $media->name,
            'file_name'  => $media->file_name,
            'mime_type'  => $media->mime_type,
            'size'       => $media->size,
            'url'        => $media->getUrl(),
            'folder'     => $media->collection_name,
            'alt'        => $media->getCustomProperty('alt', ''),
            'created_at' => $media->created_at?->toISOString(),
        ];
    }
}
