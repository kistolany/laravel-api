<?php

namespace App\Http\Controllers\ApiController\Holiday;

use App\Enums\ResponseStatus;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HolidayController extends Controller
{
    use ApiResponseTrait;

    // ── Public (permission-gated) list ────────────────────────────────────────

    public function index(): JsonResponse
    {
        return $this->success($this->fetchAll(), 'Holidays retrieved successfully.');
    }

    // ── Public feed — any authenticated user ──────────────────────────────────

    public function publicIndex(): JsonResponse
    {
        return $this->success($this->fetchAll(), 'Holidays retrieved successfully.');
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'type'        => 'required|string|in:public,national,religious,school,other',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:500',
            'document'    => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:10240',
        ]);

        [$docPath, $docName] = $this->handleUpload($request);

        $id = DB::table('holidays')->insertGetId([
            'name'          => $data['name'],
            'type'          => $data['type'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'description'   => $data['description'] ?? '',
            'document_path' => $docPath,
            'document_name' => $docName,
            'created_by'    => $request->user()?->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $holiday = DB::table('holidays')->find($id);
        $this->pushHolidayNotification($request, $holiday, 'new');

        return $this->success($this->format($holiday), 'Holiday created successfully.', 201);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table('holidays')->where('id', $id)->first();
        if (! $existing) {
            return $this->error('Holiday not found.', ResponseStatus::NOT_FOUND);
        }

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'type'            => 'required|string|in:public,national,religious,school,other',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'description'     => 'nullable|string|max:500',
            'document'        => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:10240',
            'remove_document' => 'nullable|boolean',
        ]);

        $docPath = $existing->document_path;
        $docName = $existing->document_name;

        // Remove existing doc if requested or if a new one is uploaded
        if ($request->boolean('remove_document') || $request->hasFile('document')) {
            if ($existing->document_path) {
                Storage::disk('public')->delete($existing->document_path);
            }
            $docPath = null;
            $docName = null;
        }

        if ($request->hasFile('document')) {
            [$docPath, $docName] = $this->handleUpload($request);
        }

        DB::table('holidays')->where('id', $id)->update([
            'name'          => $data['name'],
            'type'          => $data['type'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'description'   => $data['description'] ?? '',
            'document_path' => $docPath,
            'document_name' => $docName,
            'updated_at'    => now(),
        ]);

        $holiday = DB::table('holidays')->find($id);
        $this->pushHolidayNotification($request, $holiday, 'updated');

        return $this->success($this->format($holiday), 'Holiday updated successfully.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $holiday = DB::table('holidays')->where('id', $id)->first();
        if (! $holiday) {
            return $this->error('Holiday not found.', ResponseStatus::NOT_FOUND);
        }

        if ($holiday->document_path) {
            Storage::disk('public')->delete($holiday->document_path);
        }

        DB::table('holidays')->where('id', $id)->delete();

        return $this->success(null, 'Holiday deleted successfully.');
    }

    // ── Document download ─────────────────────────────────────────────────────

    public function downloadDocument(int $id)
    {
        $holiday = DB::table('holidays')->where('id', $id)->first();
        if (! $holiday || ! $holiday->document_path) {
            abort(404, 'Document not found.');
        }

        if (! Storage::disk('public')->exists($holiday->document_path)) {
            abort(404, 'File not found on disk.');
        }

        $fullPath = Storage::disk('public')->path($holiday->document_path);
        $fileName = $holiday->document_name ?? basename($holiday->document_path);
        $mimeType = File::mimeType($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function fetchAll(): array
    {
        return DB::table('holidays')
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(fn($h) => $this->format($h))
            ->values()
            ->all();
    }

    private function format(object $h): array
    {
        return [
            'id'            => $h->id,
            'name'          => $h->name,
            'type'          => $h->type,
            'start_date'    => $h->start_date,
            'end_date'      => $h->end_date,
            'description'   => $h->description ?? '',
            'document_name' => $h->document_name ?? null,
            'document_url'  => $h->document_path
                ? Storage::disk('public')->url($h->document_path)
                : null,
            'document_preview_url' => $h->document_path
                ? "/api/v1/holidays/{$h->id}/document"
                : null,
            'created_at'    => $h->created_at,
        ];
    }

    private function handleUpload(Request $request): array
    {
        if (! $request->hasFile('document')) {
            return [null, null];
        }

        $file     = $request->file('document');
        $origName = $file->getClientOriginalName();
        $path     = $file->store('holidays', 'public');

        return [$path, $origName];
    }

    private function pushHolidayNotification(Request $request, object $holiday, string $action): void
    {
        $typeLabel = match ($holiday->type) {
            'public'    => 'Public Holiday',
            'national'  => 'National Day',
            'religious' => 'Religious Holiday',
            'school'    => 'School Holiday',
            default     => 'Holiday',
        };

        $dateRange = $holiday->start_date === $holiday->end_date
            ? $holiday->start_date
            : "{$holiday->start_date} → {$holiday->end_date}";

        $title = $action === 'new'
            ? "📅 New Holiday: {$holiday->name}"
            : "📅 Holiday Updated: {$holiday->name}";

        $body = "{$typeLabel} | {$dateRange}";
        if (! empty($holiday->description)) {
            $body .= " — {$holiday->description}";
        }
        if (! empty($holiday->document_name)) {
            $body .= " 📎 {$holiday->document_name}";
        }

        DB::table('push_notifications')->insert([
            'title'      => $title,
            'body'       => $body,
            'audience'   => 'all',
            'priority'   => 'info',
            'sent_by'    => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
