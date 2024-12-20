<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture as ApiCapture;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Security\TokenInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CaptureAction::class)]
class CaptureActionTest extends TestCase
{
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

        $payment = new Payment();
        $request = new Capture($payment);
        $request->setModel($payment->getDetails());
        $this->assertTrue($action->supports($request));

        $this->assertFalse($action->supports(new Capture(null)));
        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Authorize([])));
    }

    public function testNewPaymentTriggersApiAuthorizeAndCapture(): void
    {
        $expectRequestClasses = [
            GetHumanStatus::class,
            ApiAuthorize::class,
            GetHumanStatus::class,
            ApiCapture::class,
        ];
        $runFunctions = [
            fn ($request) => $request->markNew(),
            null,
            fn ($request) => $request->markCaptured(),
            null,
        ];
        $gatewayMock = $this->createMock(GatewayInterface::class);
        $gatewayMock
            ->expects($this->exactly(count($expectRequestClasses)))
            ->method('execute')
            ->with($this->callback(function ($request) use (&$expectRequestClasses, &$runFunctions): bool {
                $class = array_shift($expectRequestClasses);
                $fn = array_shift($runFunctions);

                $this->assertTrue(class_exists($class));
                $this->assertInstanceOf($class, $request);

                if ($fn !== null) {
                    $fn($request);
                }

                return true;
            }))
        ;

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


    public function testNotNewPaymentConvertsAndTriggersApiCapture(): void
    {
        $expectRequestClasses = [
            GetHumanStatus::class,
            Convert::class,
            GetHumanStatus::class,
            ApiCapture::class,
        ];
        $runFunctions = [
            fn ($request) => $request->markPending(),
            fn ($request) => $request->setResult([]),
            fn ($request) => $request->markCaptured(),
            null,
        ];
        $gatewayMock = $this->createMock(GatewayInterface::class);
        $gatewayMock
            ->expects($this->exactly(count($expectRequestClasses)))
            ->method('execute')
            ->with($this->callback(function ($request) use (&$expectRequestClasses, &$runFunctions): bool {
                $class = array_shift($expectRequestClasses);
                $fn = array_shift($runFunctions);

                $this->assertTrue(class_exists($class));
                $this->assertInstanceOf($class, $request);

                if ($fn !== null) {
                    $fn($request);
                }

                return true;
            }))
        ;

        $action = new CaptureAction();
        $action->setGateway($gatewayMock);

        $payment = new Payment();
        $payment->setDetails(['payment_state' => 'settled']);
        $request = new Capture($payment);
        $request->setModel($payment->getDetails());

        $action->execute($request);
    }
}
