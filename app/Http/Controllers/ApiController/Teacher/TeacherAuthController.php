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

    public function show(int $id): JsonResponse
    {
        return $this->success(
            new TeacherResource($this->service->findOrFail($id)),
            'Teacher retrieved successfully.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $teacherId = $request->user()?->teacher_id;
        if (!$teacherId) {
            return $this->error('No teacher profile linked to this account.', 404);
        }
        return $this->success(
            new TeacherResource($this->service->findOrFail($teacherId)),
            'Teacher profile retrieved successfully.'
        );
    }

    public function updateSelf(Request $request): JsonResponse
    {
        $teacherId = $request->user()?->teacher_id;
        if (!$teacherId) {
            return $this->error('No teacher profile linked to this account.', 404);
        }

        $data = $request->validate([
            'phone_number'           => 'nullable|string|max:50',
            'telegram'               => 'nullable|string|max:255',
            'image'                  => 'nullable|file|image|max:2048',
            'cv_file'                => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'id_card_file'           => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'note'                   => 'nullable|string|max:5000',
            'lesson_files'           => 'nullable|array|max:20',
            'lesson_files.*'         => 'file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:10240',
            'remove_lesson_files'    => 'nullable|array',
            'remove_lesson_files.*'  => 'string',
        ]);

        return $this->success(
            new TeacherResource($this->service->updateSelf($teacherId, $data)),
            'Profile updated successfully.'
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
    public function viewFile(Request $request, int $id): StreamedResponse|JsonResponse|RedirectResponse
    {
        $field = $request->query('field', 'cv_file');

        $teacher = Teacher::findOrFail($id);

        $storedFilename = null;
        if ($field === 'lesson_file') {
            $index = (int) $request->query('index', 0);
            $files = $teacher->lesson_files ?? [];
            $entry = $files[$index] ?? null;
            $url = $entry['url'] ?? null;
            $storedFilename = $entry['name'] ?? null;
        } elseif (in_array($field, ['cv_file', 'id_card_file'], true)) {
            $url = $teacher->$field;
        } else {
            return $this->error('Invalid field.', 400);
        }

        if (!$url) {
            return $this->error('File not found.', 404);
        }

        $fetchUrl = $this->resolveCloudinaryUrl($url);

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 30,
                'follow_location' => 1,
                'header'          => "User-Agent: Sarona/1.0\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $stream = @fopen($fetchUrl, 'r', false, $ctx);

        if (!$stream) {
            return redirect($fetchUrl);
        }

        $meta        = stream_get_meta_data($stream);
        $contentType = null;
        foreach (($meta['wrapper_data'] ?? []) as $hdr) {
            if (stripos($hdr, 'Content-Type:') === 0) {
                $contentType = trim(substr($hdr, 13));
                break;
            }
        }

        // Resolve filename and MIME from URL path (prefer stored original name)
        $urlPath  = parse_url($fetchUrl, PHP_URL_PATH) ?? '';
        $filename = $storedFilename ?: (basename($urlPath) ?: 'document');
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if (!$contentType || str_starts_with($contentType, 'application/octet-stream')) {
            $contentType = $mimeMap[$ext] ?? 'application/octet-stream';
        }

        // Sniff magic bytes when MIME is still unknown
        if ($contentType === 'application/octet-stream') {
            $magic = fread($stream, 8);
            fclose($stream);
            $stream = @fopen($fetchUrl, 'r', false, $ctx);
            if (!$stream) return redirect($fetchUrl);
            if ($magic !== false) {
                if (str_starts_with($magic, '%PDF'))           { $contentType = 'application/pdf'; $filename = $filename ?: 'document.pdf'; }
                elseif (str_starts_with($magic, "\xFF\xD8\xFF")) $contentType = 'image/jpeg';
                elseif (str_starts_with($magic, "\x89PNG"))      $contentType = 'image/png';
            }
        }

        // Strip Cloudinary transformation prefix from filename if needed
        if (str_contains($filename, 'upload/') || strlen($filename) > 80) {
            $ext2 = $mimeMap[$ext] ? $ext : 'pdf';
            $filename = 'document.' . $ext2;
        }

        $disposition = $request->query('download')
            ? "attachment; filename=\"{$filename}\""
            : "inline; filename=\"{$filename}\"";

        return response()->stream(function () use ($stream) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type'                  => $contentType,
            'Content-Disposition'           => $disposition,
            'Cache-Control'                 => 'private, max-age=300',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Type',
        ]);
    }

    private function resolveCloudinaryUrl(string $url): string
    {
        // Cloudinary account has restricted delivery — generate a signed
        // download URL via the Cloudinary API that bypasses ACL restrictions.
        try {
            $cloudUrl  = (string) config('cloudinary.cloud_url', '');
            $apiKey    = parse_url($cloudUrl, PHP_URL_USER)  ?: '';
            $apiSecret = parse_url($cloudUrl, PHP_URL_PASS)  ?: '';
            $cloudName = parse_url($cloudUrl, PHP_URL_HOST)  ?: '';

            if (!$apiKey || !$apiSecret || !$cloudName) {
                return $url;
            }

            // Detect resource type from URL
            $resourceType = 'image';
            if (str_contains($url, '/raw/upload/'))   $resourceType = 'raw';
            elseif (str_contains($url, '/video/upload/')) $resourceType = 'video';

            // Extract public_id (strip version prefix, keep extension)
            if (!preg_match('#/(?:image|raw|video)/upload/(?:v\d+/)?(.+)$#', $url, $m)) {
                return $url;
            }
            $publicIdWithExt = $m[1];                                   // e.g. teacher_cv/abc.pdf
            $publicId        = preg_replace('/\.[^.]+$/', '', $publicIdWithExt); // strip ext for signing

            $timestamp = time();
            $expiresAt = $timestamp + 3600;

            // Sign exactly: expires_at + public_id + timestamp + type (no resource_type)
            $toSign    = "expires_at={$expiresAt}&public_id={$publicId}&timestamp={$timestamp}&type=upload{$apiSecret}";
            $signature = sha1($toSign);

            return "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/download"
                . "?public_id=" . urlencode($publicId)
                . "&api_key={$apiKey}"
                . "&timestamp={$timestamp}"
                . "&expires_at={$expiresAt}"
                . "&signature={$signature}"
                . "&type=upload";

        } catch (\Throwable) {
            return $url;
        }
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
