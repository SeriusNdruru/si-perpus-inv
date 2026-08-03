<?php

namespace App\Http\Controllers;

use App\Services\MediaImageService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function image(Request $request, MediaImageService $images): BinaryFileResponse
    {
        $source = $this->resolveRequestedImage($request, $images);

        return $this->fileResponse($request, $source);
    }

    public function thumbnail(Request $request, MediaImageService $images): BinaryFileResponse
    {
        $source = $this->resolveRequestedImage($request, $images);
        $size = max(48, min((int) $request->integer('size', 160), 1200));
        $thumbnail = $images->ensureThumbnail($source['path'], $size);

        return $this->fileResponse($request, $thumbnail ?? $source);
    }

    private function resolveRequestedImage(Request $request, MediaImageService $images): array
    {
        $path = (string) $request->query('path', '');

        try {
            return $images->resolveImage($path);
        } catch (RuntimeException) {
            abort(404);
        }
    }

    private function fileResponse(Request $request, array $file): BinaryFileResponse
    {
        $response = response()->file($file['absolute_path'], [
            'Content-Type' => $file['mime_type'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->setEtag(sha1($file['absolute_path'].'|'.$file['modified_at'].'|'.filesize($file['absolute_path'])));
        $response->setAutoLastModified();
        $response->isNotModified($request);

        return $response;
    }
}
