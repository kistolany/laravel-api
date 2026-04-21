<?php

namespace App\Traits;

use App\DTOs\PaginatedResult;
use Illuminate\Database\Eloquent\Builder;

trait Paginatable
{
    /**
     * This method handles the 'size' globally and returns the DTO
     */
    protected function paginateResponse(Builder $query, string $resourceClass = null): PaginatedResult
    {
        // 1. Global logic: Get 'size' or 'per_page' from URL
        $size = request()->integer('size') ?: request()->integer('per_page', 10);
        $size = max(1, min($size, 200)); // Increased max to 200 to support sorting page

        // 2. Execute Pagination (Laravel handles 'page' automatically from URL)
        $paginator = $query->paginate($size);

        // 3. Convert to your Global DTO
        return PaginatedResult::fromPaginator($paginator, $resourceClass);
    }
}