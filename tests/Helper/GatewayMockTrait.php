<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Helper;

use Closure;
use InvalidArgumentException;
use Payum\Core\GatewayInterface;

trait GatewayMockTrait
{
    /**
     * @param array<Closure> $callbacks
     */
    private function createGatewayExecuteMock(array $callbacks): GatewayInterface
    {
        $mock = $this->createMock(GatewayInterface::class);
        $mock
        ->expects($this->exactly(count($callbacks)))
        ->method('execute')
        ->with($this->callback(function ($request) use (&$callbacks): bool {
            $fn = array_shift($callbacks);
            if (!is_callable($fn)) {
                throw new InvalidArgumentException('Not a callable');
            }
            $fn($request);
            return true;
        }));
        return $mock;
    }
}
