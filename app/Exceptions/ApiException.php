<?php

namespace App\Exceptions;

use Exception;
use App\Enums\ResponseStatus;
use App\Enums\ResponseMessage;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Throwable;

class ApiException extends Exception
{
    // Use trait here
    use ApiResponseTrait;

    public function __construct(
        public ResponseStatus $status = ResponseStatus::INTERNAL_SERVER_ERROR,
        public string|ResponseMessage $responseMessage = ResponseMessage::INTERNAL_ERROR,
        public mixed $data = null,
        ?Throwable $previous = null
    ) {
        // This allows hand-written strings
        $msg = $responseMessage instanceof ResponseMessage ? $responseMessage->value : $responseMessage;
        parent::__construct($msg, $status->value, $previous);
    }

    public function render($request): JsonResponse
    {
        // Use the trait method
        return $this->coreResponse(
            $this->data,
            $this->getMessage(),
            $this->status,
          // config('app.debug') ? $this->getTraceAsString() : ""
        );
    }
}
