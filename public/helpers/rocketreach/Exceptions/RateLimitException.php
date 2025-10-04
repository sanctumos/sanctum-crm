<?php

declare(strict_types=1);

namespace RocketReach\SDK\Exceptions;

use Throwable;

/**
 * Exception thrown when rate limit is exceeded
 */
class RateLimitException extends ApiException
{
    private int $retryAfter;

    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?Throwable $previous = null,
        int $retryAfter = 60
    ) {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the retry-after value in seconds
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
