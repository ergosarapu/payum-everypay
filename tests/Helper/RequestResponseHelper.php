<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Helper;

/**
 * Helper class to store request expectations and response values.
 */
class RequestResponseHelper
{
    /**
     * @param array<string|mixed> $expectRequestBodyFieldsEqual
     * @param array<string> $expectRequestBodyHasFields
     * @param array<string> $expectRequestBodyNotHasFields
     */
    public function __construct(
        public ?string $expectRequestPath = null,
        public ?string $expectRequestMethod = null,
        public ?array $expectRequestBodyFieldsEqual = null,
        public ?array $expectRequestBodyHasFields = null,
        public ?array $expectRequestBodyNotHasFields = null,
        public ?int $responseStatusCode = null,
        public ?string $responseContents = null,
    ) {
    }
}
