<?php
namespace josemmo\Verifactu\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown by the AEAT client
 */
class AeatResponseException extends RuntimeException {
    public function __construct(
        public readonly string $response,
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}
