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
        // 1. Global logic: Get 'size' from URL for every list in the app
        $size = request()->integer('size', 10);

        // 2. Execute Pagination (Laravel handles 'page' automatically from URL)
        $paginator = $query->paginate($size);

        // 3. Convert to your Global DTO
        return PaginatedResult::fromPaginator($paginator, $resourceClass);
    }
}