<?php


namespace App\Services;

namespace App\Services;

use App\Traits\Paginatable;
use App\DTOs\PaginatedResult;
use Illuminate\Database\Eloquent\Builder;

class BaseService
{
   use Paginatable;

    protected function handleListing(Builder $query, ?string $resourceClass = null): PaginatedResult
    {
        // This leverages your Paginatable trait
        return $this->paginateResponse($query, $resourceClass);
    }
}
