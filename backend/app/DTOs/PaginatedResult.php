<?php

namespace App\DTOs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedResult
{
    public function __construct(
        public mixed $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $totalPages
    ) {}

    // Add this Global Static Method
    public static function fromPaginator(LengthAwarePaginator $paginator, string $resourceClass = null): self
    {
        return new self(
            items: $resourceClass ? $resourceClass::collection($paginator->items()) : $paginator->items(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            totalPages: $paginator->lastPage()
        );
    }
}