<?php

namespace App\Enums;

enum ResponseMessage: string
{
    case DATA_FOUND = "Data found.";
    case DATA_NOT_FOUND = "Data not found.";
    case RESOURCE_NOT_FOUND = "Resource not found.";
    case INTERNAL_ERROR = "Internal server error.";
    case SUCCESS = "Operation successful.";
}