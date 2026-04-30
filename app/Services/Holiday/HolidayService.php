<?php

namespace App\Services\Holiday;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Services\Concerns\ServiceTraceable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HolidayService
{
    use ServiceTraceable;

    public function list(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return DB::table('holidays')
                ->orderBy('start_date', 'asc')
                ->get()
                ->values();
        });
    }

    public function create(array $data, ?UploadedFile $document, ?Authenticatable $user): object
    {
        return $this->trace(__FUNCTION__, function () use ($data, $document, $user): object {
            [$documentPath, $documentName] = $this->storeDocument($document);

            $id = DB::table('holidays')->insertGetId([
                'name' => $data['name'],
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? '',
                'document_path' => $documentPath,
                'document_name' => $documentName,
                'created_by' => $user?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $holiday = $this->findOrFail($id);
            $this->pushHolidayNotification($holiday, $user, 'new');

            return $holiday;
        });
    }

    public function update(int $id, array $data, ?UploadedFile $document, ?Authenticatable $user): object
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data, $document, $user): object {
            $holiday = $this->findOrFail($id);
            $documentPath = $holiday->document_path;
            $documentName = $holiday->document_name;

            if (($data['remove_document'] ?? false) || $document) {
                $this->deleteDocument($documentPath);
                $documentPath = null;
                $documentName = null;
            }

            if ($document) {
                [$documentPath, $documentName] = $this->storeDocument($document);
            }

            DB::table('holidays')->where('id', $id)->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? '',
                'document_path' => $documentPath,
                'document_name' => $documentName,
                'updated_at' => now(),
            ]);

            $updatedHoliday = $this->findOrFail($id);
            $this->pushHolidayNotification($updatedHoliday, $user, 'updated');

            return $updatedHoliday;
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $holiday = $this->findOrFail($id);

            $this->deleteDocument($holiday->document_path);
            DB::table('holidays')->where('id', $id)->delete();
        });
    }

    public function documentResponse(int $id): BinaryFileResponse
    {
        return $this->trace(__FUNCTION__, function () use ($id): BinaryFileResponse {
            $holiday = $this->findOrFail($id);

            if (! $holiday->document_path) {
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
        });
    }

    private function findOrFail(int $id): object
    {
        $holiday = DB::table('holidays')->where('id', $id)->first();

        if (! $holiday) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Holiday not found.');
        }

        return $holiday;
    }

    private function storeDocument(?UploadedFile $document): array
    {
        if (! $document) {
            return [null, null];
        }

        return [
            $document->store('holidays', 'public'),
            $document->getClientOriginalName(),
        ];
    }

    private function deleteDocument(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function pushHolidayNotification(object $holiday, ?Authenticatable $user, string $action): void
    {
        DB::table('push_notifications')->insert([
            'title' => $this->notificationTitle($holiday, $action),
            'body' => $this->notificationBody($holiday),
            'audience' => 'all',
            'priority' => 'info',
            'sent_by' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notificationTitle(object $holiday, string $action): string
    {
        return $action === 'new'
            ? "New Holiday: {$holiday->name}"
            : "Holiday Updated: {$holiday->name}";
    }

    private function notificationBody(object $holiday): string
    {
        $typeLabel = match ($holiday->type) {
            'public' => 'Public Holiday',
            'national' => 'National Day',
            'religious' => 'Religious Holiday',
            'school' => 'School Holiday',
            default => 'Holiday',
        };

        $dateRange = $holiday->start_date === $holiday->end_date
            ? $holiday->start_date
            : "{$holiday->start_date} to {$holiday->end_date}";

        $body = "{$typeLabel} | {$dateRange}";

        if (! empty($holiday->description)) {
            $body .= " - {$holiday->description}";
        }

        if (! empty($holiday->document_name)) {
            $body .= " Attachment: {$holiday->document_name}";
        }

        return $body;
    }
}
