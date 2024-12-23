<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture as ApiCapture;
use ErgoSarapu\PayumEveryPay\Tests\Helper\GatewayMockTrait;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Security\TokenInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CaptureAction::class)]
class CaptureActionTest extends TestCase
{
    use GatewayMockTrait;

    public function testImplements(): void
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
        $this->assertFalse($action->supports(new Authorize([])));
    }

    public function testNewPaymentTriggersApiAuthorizeAndCapture(): void
    {
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHumanStatus $request) => $request->markNew(),
            fn (ApiAuthorize $request) => null,
            fn (ApiCapture $request) => null,
        ]);

        $action = new CaptureAction();
        $action->setGateway($gatewayMock);

        $tokenMock = $this->createMock(TokenInterface::class);
        $tokenMock->method('getAfterUrl')->willReturn('http://localhost');
        $captureMock = $this->createMock(Capture::class);
        $captureMock->method('getToken')->willReturn($tokenMock);

        $payment = new Payment();
        $request = new Capture($tokenMock);
        $request->setModel($payment); // Sets the model and firstModel
        $request->setModel($payment->getDetails()); // Overrides the model, but keeps firstModel

        $action->execute($request);
    }

}
