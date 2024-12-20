<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action;

use ErgoSarapu\PayumEveryPay\Action\StatusAction;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetBinaryStatus;
use Payum\Core\Request\GetHumanStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusAction::class)]
class StatusActionTest extends TestCase
{
    public function testImplements(): void
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

    public function testMarkNewIfPaymentStateNotExists(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['foo' => 'bar']));
        $this->assertTrue($request->isNew());
    }

    public function testMarkUnknownIfUnknownPaymentState(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'foobar']));
        $this->assertTrue($request->isUnknown());
    }

    public function testMarkPendingIfPaymentStateInitial(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'initial']));
        $this->assertTrue($request->isPending());
    }

    public function testMarkPendingIfPaymentStateSentForProcessing(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'sent_for_processing']));
        $this->assertTrue($request->isPending());
    }

    public function testMarkPendingIfPaymentStateWaitingFor3dsResponse(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'waiting_for_3ds_response']));
        $this->assertTrue($request->isPending());
    }

    public function testMarkPendingIfPaymentStateWaitingForSca(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'waiting_for_sca']));
        $this->assertTrue($request->isPending());
    }

    public function testMarkPendingIfPaymentStateConfirmed3ds(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'confirmed_3ds']));
        $this->assertTrue($request->isPending());
    }

    public function testMarkExpiredIfPaymentStateAbandoned(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'abandoned']));
        $this->assertTrue($request->isExpired());
    }

    public function testMarkFailedIfPaymentStateFailed(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'failed']));
        $this->assertTrue($request->isFailed());
    }

    public function testMarkCapturedIfPaymentStateSettled(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'settled']));
        $this->assertTrue($request->isCaptured());
    }

    public function testMarkCanceledIfPaymentStateVoided(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'voided']));
        $this->assertTrue($request->isCanceled());
    }

    public function testMarkRefundedIfPaymentStateRefunded(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'refunded']));
        $this->assertTrue($request->isRefunded());
    }

    public function testMarkSuspendedIfPaymentStateChargeBacked(): void
    {
        $action = new StatusAction();
        $action->execute($request = new GetHumanStatus(['payment_state' => 'chargebacked']));
        $this->assertTrue($request->isSuspended());
    }

}
