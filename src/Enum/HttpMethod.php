<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case MULTIPART = 'MULTIPART';
}
