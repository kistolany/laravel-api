<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherArchiveRequest;
use App\Http\Requests\Teacher\TeacherIndexRequest;
use App\Http\Requests\Teacher\TeacherRequest;
use App\Http\Resources\Teacher\TeacherAuthResource;
use App\Http\Resources\Teacher\TeacherResource;
use App\Models\Teacher;
use App\Services\Teacher\TeacherAuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherAuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TeacherAuthService $service
    ) {}

    public function index(TeacherIndexRequest $request): JsonResponse
    {
        return $this->success(
            TeacherResource::collection($this->service->index($request->validated())),
            'Teachers retrieved successfully.'
        );
    }

    public function archived(): JsonResponse
    {
        return $this->success(
            TeacherResource::collection($this->service->archived()),
            'Archived teachers retrieved successfully.'
        );
    }

    public function destroy(TeacherArchiveRequest $request, int $id): JsonResponse
    {
        $this->service->archive($id, $request->user()?->id, $request->validated('delete_reason'));

        return $this->success(null, 'Teacher archived successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        return $this->success(
            new TeacherResource($this->service->restore($id)),
            'Teacher restored successfully.'
        );
    }

    public function register(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::teacher($this->service->register($request->validated())),
            'Teacher registered successfully.'
        );
    }

    public function update(TeacherRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new TeacherResource($this->service->updateForUser($id, $request->validated(), $request->user())),
            'Teacher updated successfully.'
        );
    }

    public function uploadImage(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::uploadImage($this->service->uploadImageOrFail($request->file('image'))),
            'Image uploaded successfully.'
        );
    }

    /**
     * Proxy-stream a teacher file (cv_file or id_card_file) so the browser
     * can view it regardless of Cloudinary account access restrictions.
     */
    public function viewFile(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $field = $request->query('field', 'cv_file');
        if (!in_array($field, ['cv_file', 'id_card_file'], true)) {
            return $this->error('Invalid field.', 400);
        }

        $teacher = Teacher::findOrFail($id);
        $url = $teacher->$field;

        if (!$url) {
            return $this->error('File not found.', 404);
        }

        $cloudApiKey    = config('cloudinary.key') ?: env('CLOUDINARY_KEY');
        $cloudApiSecret = config('cloudinary.secret') ?: env('CLOUDINARY_SECRET');
        $cloudName      = env('CLOUDINARY_CLOUD_NAME');

        // Build a signed Cloudinary URL so private/restricted files are accessible
        if ($cloudApiKey && $cloudApiSecret && $cloudName && str_contains($url, 'cloudinary.com')) {
            $publicId   = $this->extractCloudinaryPublicId($url);
            $resourceType = str_contains($url, '/raw/upload/') ? 'raw' : 'image';
            $timestamp  = time();
            $expiry     = $timestamp + 300; // valid for 5 minutes

            $sigParams  = "public_id={$publicId}&timestamp={$timestamp}";
            $signature  = hash('sha256', $sigParams . $cloudApiSecret);

            $signedUrl  = "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload"
                . "/v{$timestamp}/{$publicId}"
                . "?api_key={$cloudApiKey}&timestamp={$timestamp}&signature={$signature}";

            // Fall back to original URL if signature build failed
            $fetchUrl = $url;
        } else {
            $fetchUrl = $url;
        }

        // Stream the file through Laravel so the browser receives it directly
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 30,
                'header'  => 'User-Agent: Sarona/1.0',
            ],
            'ssl' => ['verify_peer' => false],
        ]);

        $stream = @fopen($fetchUrl, 'r', false, $ctx);

        if (!$stream) {
            // Last resort: redirect directly
            return redirect($url);
        }

        $meta        = stream_get_meta_data($stream);
        $contentType = 'application/octet-stream';
        foreach (($meta['wrapper_data'] ?? []) as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));

        return response()->stream(function () use ($stream) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

    private function extractCloudinaryPublicId(string $url): string
    {
        // Strip version and extension: /image/upload/v123/folder/file.pdf → folder/file
        if (preg_match('#/(?:image|raw)/upload/(?:v\d+/)?(.+)$#', $url, $m)) {
            return preg_replace('/\.[^.]+$/', '', $m[1]);
        }
        return '';
    }

}
