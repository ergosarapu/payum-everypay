<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Request\Api\OneOff;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Security\TokenInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CaptureAction::class)]
class CaptureActionTest extends TestCase
{
    public function testShouldImplements(): void
    {
        $action = new CaptureAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new CaptureAction();

        $this->assertTrue($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Capture(null)));
        $this->assertFalse($action->supports(new Authorize(null)));
    }

    public function testTriggersOneOffApiRequest(): void
    {
        $gatewayMock = $this->createMock(GatewayInterface::class);
        $gatewayMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(OneOff::class))
        ;

        $action = new CaptureAction();
        $action->setGateway($gatewayMock);

        $tokenMock = $this->createMock(TokenInterface::class);
        $tokenMock
            ->method('getAfterUrl')->willReturn('http://localhost');
        $captureMock = $this->createMock(Capture::class);
        $captureMock->method('getToken')->willReturn($tokenMock);

        $request = new Capture($tokenMock);
        $request->setModel([]);
        $action->execute($request);
    }
}
