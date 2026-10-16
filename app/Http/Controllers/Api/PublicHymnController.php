<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Hymns\HymnCatalog;
use App\Http\Controllers\Controller;
use App\Services\Hymns\HymnAddon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicHymnController extends Controller
{
    public function __construct(private readonly HymnAddon $addon) {}

    public function status(): JsonResponse
    {
        return $this->json($this->addon->status());
    }

    public function books(): JsonResponse
    {
        return $this->json(['books' => $this->catalog()->books()]);
    }

    public function index(Request $request): JsonResponse
    {
        $catalog = $this->catalog();
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:200'], 'book_id' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:120'], 'letter' => ['nullable', 'regex:/^[A-Za-z#]$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return $this->json($catalog->search($filters));
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->catalog()->hymn($id);
        abort_if($item === null, 404);

        return $this->json(['hymn' => $item]);
    }

    public function manifest(int $bookId): JsonResponse
    {
        $manifest = $this->catalog()->manifest($bookId);
        abort_if($manifest === null, 404);

        return $this->json($manifest);
    }

    public function download(int $bookId, string $revision): BinaryFileResponse
    {
        $file = $this->catalog()->download($bookId, $revision);
        abort_if($file === null, 404);

        return response()->download($file['path'], $file['filename'], [
            'Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store, private',
            'X-Content-SHA256' => $file['sha256'], 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function legacy(array $data): JsonResponse
    {
        $catalog = $this->addon->status()['enabled'] ? $this->addon->catalog() : null;
        if (! $catalog) {
            return $this->json(['status' => 'ok', 'hymns' => [], 'isLastPage' => true]);
        }
        $validated = Validator::make($data, [
            'query' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ])->validate();
        $result = $catalog->search(['q' => $validated['query'] ?? '',
            'page' => ((int) ($validated['page'] ?? 0)) + 1, 'limit' => 20]);

        return $this->json(['status' => 'ok', 'hymns' => $result['items'], 'isLastPage' => ! $result['has_more']]);
    }

    private function catalog(): HymnCatalog
    {
        $status = $this->addon->status();
        $catalog = $status['enabled'] ? $this->addon->catalog() : null;
        if (! $catalog) {
            throw new HttpResponseException($this->json([
                'code' => 'hymn_disabled', 'message' => 'Hymns are temporarily unavailable.',
                'reason' => $status['reason'],
            ])->setStatusCode(409));
        }

        return $catalog;
    }

    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
