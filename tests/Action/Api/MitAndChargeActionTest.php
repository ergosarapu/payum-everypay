<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\MitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authorize::class)]
#[CoversClass(MitAndChargeAction::class)]
class MitAndChargeActionTest extends TestCase
{
    public function testImplements(): void
    {
        $action = new MitAndChargeAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new MitAndChargeAction();

        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::MIT])));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Authorize([])));
        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Capture(null)));
    }

    public function testCallsApiMitAndCharge(): void
    {
        $apiMock = $this->createMock(Api::class);
        $apiMock
            ->expects($this->once())
            ->method('doMit')
            ->willReturn(['payment_state' => 'settled']);
        $apiMock
            ->expects($this->once())
            ->method('doCharge')
            ->willReturn(['payment_state' => 'settled']);

        $action = new MitAndChargeAction();
        $action->setApi($apiMock);

        $request = new Authorize(['_type' => PaymentType::MIT]);
        $action->execute($request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertNotNull($model['merchant_ip']);
    }

}
