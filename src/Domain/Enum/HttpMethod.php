<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\Enum;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Head = 'HEAD';
    case Options = 'OPTIONS';
    case Delete = 'DELETE';
}
