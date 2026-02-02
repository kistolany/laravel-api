<?php

namespace App\Enums;

enum ResponseStatus: int
{
    case SUCCESS = 200;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case EXISTING_DATA = 422;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case INTERNAL_SERVER_ERROR = 500;

    public function text(): string
    {
        return match($this) {
            self::SUCCESS => 'success',
            self::BAD_REQUEST => 'bad_request',
            self::UNAUTHORIZED => 'unauthorized',
            self::FORBIDDEN => 'no_permission_access_is_denied',
            self::EXISTING_DATA => 'Error_dublicate_data',
            self::NOT_FOUND => 'not_found',
            self::INTERNAL_SERVER_ERROR => 'internal_server_error',
        };
    }
}