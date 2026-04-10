<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    private const MODE_USER = 'user';
    private const MODE_PROFILE = 'profile';
    private const MODE_STATUS = 'status';

    private string $mode = self::MODE_USER;

    public static function user(mixed $resource): self
    {
        return (new self($resource))->asUser();
    }

    public static function profile(mixed $resource): self
    {
        return (new self($resource))->asProfile();
    }

    public static function status(mixed $resource): self
    {
        return (new self($resource))->asStatus();
    }

    public function asUser(): self
    {
        $this->mode = self::MODE_USER;

        return $this;
    }

    public function asProfile(): self
    {
        $this->mode = self::MODE_PROFILE;

        return $this;
    }

    public function asStatus(): self
    {
        $this->mode = self::MODE_STATUS;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return match ($this->mode) {
            self::MODE_PROFILE => $this->toProfileArray(),
            self::MODE_STATUS => $this->toStatusArray(),
            default => $this->toUserArray(),
        };
    }

    private function toUserArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'phone' => $this->phone,
            'image' => $this->image,
            'status' => $this->status,
            'role' => $this->role?->name,
        ];
    }

    private function toProfileArray(): array
    {
        return [
            ...$this->toUserArray(),
            'permissions' => $this->role?->permissions->pluck('name')->values(),
        ];
    }

    private function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'status' => $this->status,
            'role' => $this->role?->name,
        ];
    }
}


