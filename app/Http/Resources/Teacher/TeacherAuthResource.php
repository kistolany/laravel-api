<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAuthResource extends JsonResource
{
    private const MODE_TEACHER = 'teacher';
    private const MODE_TOKENS = 'tokens';
    private const MODE_UPLOAD_IMAGE = 'upload_image';

    private string $mode = self::MODE_TEACHER;

    public static function teacher(mixed $resource): self
    {
        return (new self($resource))->asTeacher();
    }

    public static function tokens(mixed $resource): self
    {
        return (new self($resource))->asTokens();
    }

    public static function uploadImage(mixed $resource): self
    {
        return (new self($resource))->asUploadImage();
    }

    public function asTeacher(): self
    {
        $this->mode = self::MODE_TEACHER;

        return $this;
    }

    public function asTokens(): self
    {
        $this->mode = self::MODE_TOKENS;

        return $this;
    }

    public function asUploadImage(): self
    {
        $this->mode = self::MODE_UPLOAD_IMAGE;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return match ($this->mode) {
            self::MODE_TOKENS => $this->toTokensArray(),
            self::MODE_UPLOAD_IMAGE => $this->toUploadImageArray(),
            default => $this->toTeacherArray(),
        };
    }

    private function toTeacherArray(): array
    {
        return [
            'teacher' => new TeacherResource($this->resource),
        ];
    }

    private function toTokensArray(): array
    {
        return [
            'access_token' => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'],
            'token_type' => $this->resource['token_type'],
            'expires_in' => $this->resource['expires_in'],
            'teacher' => new TeacherResource($this->resource['teacher']),
        ];
    }

    private function toUploadImageArray(): array
    {
        return [
            'image_url' => $this->resource,
        ];
    }
}

