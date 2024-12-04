<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\OneOffAction;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Request\Api\OneOff;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpRedirect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OneOffAction::class)]
class OneOffActionTest extends TestCase
{
    public function testShouldImplements(): void
    {
        $action = new OneOffAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new OneOffAction();

        $this->assertTrue($action->supports(new OneOff([])));
        $this->assertFalse($action->supports(new OneOff(null)));
        $this->assertFalse($action->supports(new OneOff(null)));
    }

    public function testThrowsRedirectOnResponse(): void
    {
        $apiMock = $this->createMock(Api::class);
        $apiMock
            ->expects($this->once())
            ->method('doOneOff')
            ->willReturn(['payment_link' => 'https://example.com'])
        ;

        $gatewayMock = $this->createMock(GatewayInterface::class);
        $gatewayMock
            ->expects($this->once())
            ->method('execute');

        $action = new OneOffAction();
        $action->setApi($apiMock);
        $action->setGateway($gatewayMock);

        $this->expectExceptionObject(new HttpRedirect('https://example.com'));
        $action->execute(new OneOff([]));
    }
}
