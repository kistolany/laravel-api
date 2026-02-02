<?php


namespace App\Traits;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Enums\ResponseMessage;
use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    private function coreResponse(mixed $data, string $message, ResponseStatus $status, string $trace = ""): JsonResponse
    {
        return response()->json([
            'datetime'  => now()->toDateTimeString(),
            'timestamp' => now()->getTimestampMs(),
            'status'    => $status->text(),
            'code'      => $status->value,
            'message'   => $message,
            'data'      => $data,
            'trace'     => $trace
        ], $status->value);
    }

    /**
     * Handles Success Responses (Detects Pagination automatically)
     */
    public function success(mixed $data = null, ResponseMessage|string $message = ResponseMessage::SUCCESS): JsonResponse
    {
        // 1. Convert Enum or String message
        $msg = $message instanceof ResponseMessage ? $message->value : $message;

        // 2. AUTOMATIC PAGINATION CHECK: 
        // If $data is our custom Page class, rewrite the structure before sending to coreResponse
        if ($data instanceof PaginatedResult) {
            $data = [
                'items' => $data->items,
                'pagination' => [
                    'total' => $data->total,
                    'per_page'     => $data->perPage,
                    'current_page' => $data->currentPage,
                    'total_pages'  => $data->totalPages,
                ]
            ];
        }

        return $this->coreResponse($data, $msg, ResponseStatus::SUCCESS);
    }



    public function error(string|ResponseMessage $message, ResponseStatus $status = ResponseStatus::BAD_REQUEST, string $trace = ""): JsonResponse
    {
        $msg = $message instanceof ResponseMessage ? $message->value : $message;
        return $this->coreResponse(null, $msg, $status, $trace);
    }
}
