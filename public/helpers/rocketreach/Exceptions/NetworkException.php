<?php

declare(strict_types=1);

namespace RocketReach\SDK\Exceptions;

use Throwable;

/**
 * Exception thrown for network-related errors
 */
class NetworkException extends ApiException
{
    public function __construct(
        string $message = 'Network error occurred',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
