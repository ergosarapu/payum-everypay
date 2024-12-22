<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\AuthorizeAction;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthorizeAction::class)]
class AuthorizeActionTest extends TestCase
{
    public function testImplements(): void
    {
        $action = new AuthorizeAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new AuthorizeAction();

        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::MIT])));
        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::CIT])));
        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::ONE_OFF])));
        $this->assertTrue($action->supports(new Authorize([])));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Capture(null)));
        $this->assertFalse($action->supports(new Capture([])));
    }

}
