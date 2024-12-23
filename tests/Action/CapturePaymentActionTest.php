<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\CapturePaymentAction;
use ErgoSarapu\PayumEveryPay\Tests\Helper\GatewayMockTrait;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Convert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CapturePaymentAction::class)]
class CapturePaymentActionTest extends TestCase
{
    use GatewayMockTrait;

    public function testImplements(): void
    {
        $action = new CapturePaymentAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }


    public function testSupports(): void
    {
        $action = new CapturePaymentAction();

        $this->assertTrue($action->supports(new Capture(new Payment())));
        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Capture(null)));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Authorize([])));
    }


    public function testPaymentConvertsAndUpdatesModelAndTriggersCapture(): void
    {
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (Convert $request) => $request->setResult(['foo' => 'bar']),
            fn (Capture $request) => null,
        ]);

        $action = new CapturePaymentAction();
        $action->setGateway($gatewayMock);

        $payment = new Payment();
        $payment->setDetails(['foo2' => 'bar2']);
        $request = new Capture($payment);

        $action->execute($request);
        $details = ArrayObject::ensureArrayObject($payment->getDetails());
        $this->assertEquals('bar', $details['foo']);
        $this->assertEquals('bar2', $details['foo2']);
    }
}
