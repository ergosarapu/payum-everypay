<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\StatusAction;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetBinaryStatus;
use Payum\Core\Request\GetHumanStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusAction::class)]
class StatusActionTest extends TestCase
{
    public function testShouldImplements(): void
    {
        $action = new StatusAction();

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
        $this->assertNotInstanceOf(ApiAwareInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new StatusAction();

        $this->assertTrue($action->supports(new GetHumanStatus([])));
        $this->assertTrue($action->supports(new GetBinaryStatus([])));
        $this->assertFalse($action->supports(new Capture([])));
    }

    public function testShouldMarkNewIfPaymentStateNotExists(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['foo' => 'bar']));
        $this->assertTrue($request->isNew());
    }

    public function testShouldThrowIfUnknownPaymentState(): void
    {
        $action = new StatusAction();
        $this->expectException(InvalidArgumentException::class);
        $action->execute(new GetHumanStatus(['payment_state' => 'foobar']));
    }

    public function testShouldMarkPendingIfPaymentStateInitial(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'initial']));
        $this->assertTrue($request->isPending());
    }

    // TODO: Waiting for 3DS

    // TODO: Waiting for SCA (Strong Customer Authentication)

    // TODO: Sent for processing

    public function testShouldMarkExpiredIfPaymentStateAbandoned(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'abandoned']));
        $this->assertTrue($request->isExpired());
    }

    public function testShouldMarkFailedIfPaymentStateFailed(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'failed']));
        $this->assertTrue($request->isFailed());
    }

    public function testShouldMarkCapturedIfPaymentStateSettled(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'settled']));
        $this->assertTrue($request->isCaptured());
    }

    public function testShouldMarkAuthorizedIfPaymentStateAuthorized(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'authorized']));
        $this->assertTrue($request->isAuthorized());
    }

    public function testShouldMarkCanceledIfPaymentStateVoided(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'voided']));
        $this->assertTrue($request->isCanceled());
    }

    public function testShouldMarkRefundedIfPaymentStateRefunded(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'refunded']));
        $this->assertTrue($request->isRefunded());
    }

    // TODO: Charged Back

    // TODO: 3DS Confirmed
}
