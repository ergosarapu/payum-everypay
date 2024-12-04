<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ArrayAccess;
use ErgoSarapu\PayumEveryPay\Action\ConvertPaymentAction;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Convert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConvertPaymentAction::class)]
class ConvertPaymentActionTest extends TestCase
{
    public function testShouldImplements(): void
    {
        $action = new ConvertPaymentAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new ConvertPaymentAction();

        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Convert(null, 'string')));
        $this->assertFalse($action->supports(new Convert([], 'array')));
        $this->assertTrue($action->supports(new Convert(new Payment(), 'array')));
    }

    public function testShouldCorrectlyConvertPaymentToDetailsAndSetItBack(): void
    {
        $payment = new Payment();
        $payment->setTotalAmount(123);
        // These are allowed characters according to the API spec
        $payment->setNumber("a-zA-Z0-9/-?:().,'+");
        $payment->setClientEmail('example@example.com');

        $request = new Convert($payment, 'array');

        $action = new ConvertPaymentAction();

        $supports = $action->supports($request);
        $this->assertTrue($supports);

        $action->execute($request);

        $details = $request->getResult();

        $this->assertInstanceOf(ArrayAccess::class, $details);
        $this->assertArrayHasKey('amount', $details);
        $this->assertArrayHasKey('order_reference', $details);
        $this->assertArrayHasKey('email', $details);

        $this->assertEquals(1.23, $details['amount']);
        $this->assertEquals("a-zA-Z0-9/-?:().,'+", $details['order_reference']);
        $this->assertEquals('example@example.com', $details['email']);
    }

}
