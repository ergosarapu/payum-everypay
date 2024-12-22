<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\NotifyAction;
use ErgoSarapu\PayumEveryPay\Const\PaymentState;
use ErgoSarapu\PayumEveryPay\Tests\Helper\GatewayMockTrait;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Sync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotifyAction::class)]
class NotifyActionTest extends TestCase
{
    use GatewayMockTrait;

    public function testImplements(): void
    {
        $action = new NotifyAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertInstanceOf(GatewayAwareInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }


    public function testSupports(): void
    {
        $action = new NotifyAction();

        $request = new Notify(new Payment());
        $request->setModel([]);
        $this->assertTrue($action->supports($request));
        $this->assertFalse($action->supports(new Notify(null)));
        $this->assertFalse($action->supports(new Notify([])));
    }

    public function testThrowsHttpResponseIfPaymentReferenceMissing(): void
    {
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHttpRequest $request) => $request->query['foo'] = 'bar',
        ]);

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);

        $payment = new Payment();
        $payment->setDetails([]);
        $request = new Notify($payment);
        $request->setModel($payment->getDetails());

        $exception = null;
        try {
            $action->execute($request);
        } catch (HttpResponse $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertEquals(400, $exception->getStatusCode());
    }

    public function testShouldSyncAndGetStatus(): void
    {
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHttpRequest $request) => $request->query['payment_reference'] = '123',
            function (Sync $request) {
                $model = ArrayObject::ensureArrayObject($request->getModel());
                $model['payment_state'] = PaymentState::SETTLED;
            },
            fn (GetHumanStatus $request) => null,
        ]);

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);

        $payment = new Payment();
        $payment->setDetails([]);
        $request = new Notify($payment);
        $request->setModel($payment->getDetails());

        $action->execute($request);
    }

    public function testAutoCaptureTriggersCaptureIfStatusCaptured(): void
    {
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHttpRequest $request) => $request->query['payment_reference'] = '123',
            function (Sync $request) {
                $model = ArrayObject::ensureArrayObject($request->getModel());
                $model['payment_state'] = PaymentState::SETTLED;
            },
            fn (GetHumanStatus $request) => $request->markCaptured(),
            fn (Capture $request) => null
        ]);

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);

        $payment = new Payment();
        $payment->setDetails(['_auto_capture_with_notify' => NotifyAction::AUTO_CAPTURE_QUEUED]);
        $request = new Notify($payment);
        $request->setModel($payment->getDetails());

        $action->execute($request);
        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals(NotifyAction::AUTO_CAPTURE_TRIGGERED, $model['_auto_capture_with_notify']);
    }
}
