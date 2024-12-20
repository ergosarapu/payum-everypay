<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\CaptureAction;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Capture::class)]
#[CoversClass(CaptureAction::class)]
class CaptureActionTest extends TestCase
{
    public function testImplements(): void
    {
        $action = new CaptureAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new CaptureAction();
        $this->assertTrue($action->supports(new Capture(['_type' => PaymentType::CIT, 'payment_reference' => 'abc'])));
        $this->assertTrue($action->supports(new Capture(['_type' => PaymentType::MIT, 'payment_reference' => 'abc'])));
        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Capture(null)));
    }

}
