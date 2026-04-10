<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Services\Concerns\ServiceTraceable;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;

class BaseService
{
    use ServiceTraceable;
    use Paginatable;

    protected function handleListing(Builder $query, ?string $resourceClass = null): PaginatedResult
    {
        return $this->paginateResponse($query, $resourceClass);
    }
}
