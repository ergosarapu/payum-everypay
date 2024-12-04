<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\NotifyAction;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Sync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotifyAction::class)]
class NotifyActionTest extends TestCase
{
    public function testShouldImplements(): void
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

        $this->assertFalse($action->supports(new Notify(null)));
        $this->assertTrue($action->supports(new Notify([])));
    }

    public function testShouldThrowHttpResponseIfPaymentReferenceMissing(): void
    {
        $gatewayMock = $this->createMock(GatewayInterface::class);
        $gatewayMock
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query['foo'] = 'bar';
            });
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $request = new Notify([]);

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
        $gatewayMock = $this->createMock(GatewayInterface::class);
        $counter = 0;
        $gatewayMock
            ->expects($this->exactly(3))
            ->method('execute')
            ->with(
                $this->callback(
                    function (mixed $request) use (&$counter) {
                        $counter++;
                        if ($counter === 1) {
                            $this->assertInstanceOf(GetHttpRequest::class, $request);
                            $request->query['payment_reference'] = '123';
                            return true;
                        }
                        if ($counter === 2) {
                            $this->assertInstanceOf(Sync::class, $request);
                            return true;
                        }
                        if ($counter === 3) {
                            $this->assertInstanceOf(GetHumanStatus::class, $request);
                            return true;
                        }
                        return false;
                    }
                )
            );

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $request = new Notify([]);

        $action->execute($request);
    }
}
