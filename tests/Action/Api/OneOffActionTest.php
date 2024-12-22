<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\OneOffAction;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize;
use ErgoSarapu\PayumEveryPay\Tests\Helper\GatewayMockTrait;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authorize::class)]
#[CoversClass(OneOffAction::class)]
class OneOffActionTest extends TestCase
{
    use GatewayMockTrait;

    public function testImplements(): void
    {
        $action = new OneOffAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new OneOffAction();

        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::ONE_OFF])));
        $this->assertFalse($action->supports(new Authorize([])));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Authorize(null)));
    }

    public function testThrowsRedirectOnResponse(): void
    {
        $apiMock = $this->createMock(Api::class);
        $apiMock
            ->expects($this->once())
            ->method('doOneOff')
            ->willReturn(['payment_link' => 'https://example.com'])
        ;

        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHumanStatus $request) => $request->markNew(),
            fn (GetHttpRequest $request) => $request->clientIp = '127.0.0.1',
        ]);

        $action = new OneOffAction();
        $action->setApi($apiMock);
        $action->setGateway($gatewayMock);

        $this->expectExceptionObject(new HttpRedirect('https://example.com'));
        $action->execute(new Authorize(['_type' => PaymentType::ONE_OFF]));
    }

    public function testThrowsRedirectIfPendingWithPaymentLink(): void
    {
        $apiMock = $this->createMock(Api::class);
        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHumanStatus $request) => $request->markPending(),
        ]);

        $action = new OneOffAction();
        $action->setApi($apiMock);
        $action->setGateway($gatewayMock);

        $this->expectExceptionObject(new HttpRedirect('https://example.com'));
        $action->execute(new Authorize(['_type' => PaymentType::ONE_OFF, 'payment_link' => 'https://example.com']));
    }
}
