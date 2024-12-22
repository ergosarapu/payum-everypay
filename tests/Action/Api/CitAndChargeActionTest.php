<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\CitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture;
use ErgoSarapu\PayumEveryPay\Tests\Helper\GatewayMockTrait;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authorize::class)]
#[CoversClass(CitAndChargeAction::class)]
class CitAndChargeActionTest extends TestCase
{
    use GatewayMockTrait;

    public function testImplements(): void
    {
        $action = new CitAndChargeAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new CitAndChargeAction();

        $this->assertTrue($action->supports(new Authorize(['_type' => PaymentType::CIT])));
        $this->assertFalse($action->supports(new Authorize(null)));
        $this->assertFalse($action->supports(new Authorize([])));
        $this->assertFalse($action->supports(new Capture([])));
        $this->assertFalse($action->supports(new Capture(null)));
    }

    public function testCallsApiCitAndCharge(): void
    {
        $apiMock = $this->createMock(Api::class);
        $apiMock
            ->expects($this->once())
            ->method('doCit')
            ->willReturn(['payment_state' => 'settled']);
        $apiMock
            ->expects($this->once())
            ->method('doCharge')
            ->willReturn(['payment_state' => 'settled', 'payment_link' => 'https://igw-demo.every-pay.com/foo/bar']);

        $gatewayMock = $this->createGatewayExecuteMock([
            fn (GetHttpRequest $request) => $request->clientIp = '127.0.0.1',
        ]);

        $action = new CitAndChargeAction();
        $action->setApi($apiMock);
        $action->setGateway($gatewayMock);

        $request = new Authorize(['_type' => PaymentType::CIT, 'customer_url' => 'https://example.com']);

        $exception = null;
        try {
            $action->execute($request);
        } catch (HttpRedirect $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertEquals('https://igw-demo.every-pay.com/foo/bar', $exception->getUrl());
    }

}
