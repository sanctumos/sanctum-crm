<?php

declare(strict_types=1);

namespace RocketReach\SDK\Exceptions;

use Exception;
use Throwable;

/**
 * Base API exception class
 * 
 * Represents errors returned by the RocketReach API
 */
class ApiException extends Exception
{
    private array $responseData;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $responseData = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    /**
     * Get the response data from the API
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * Check if this is a client error (4xx)
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    /**
     * Check if this is a server error (5xx)
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->code >= 500;
    }
}
