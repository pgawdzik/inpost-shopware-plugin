<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Api\Exception;

final class InPostApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'api_error',
        private readonly int $httpStatus = 502
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
