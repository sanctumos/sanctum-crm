<?php

declare(strict_types=1);

namespace RocketReach\SDK\Exceptions;

/**
 * Exception thrown when API key is invalid or missing
 */
class InvalidApiKeyException extends ApiException
{
    public function __construct(string $message = 'Invalid or missing API key')
    {
        parent::__construct($message, 401);
    }
}
