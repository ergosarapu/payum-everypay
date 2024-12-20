<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use Assert\Assertion;
use ErgoSarapu\PayumEveryPay\Const\PaymentState;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\GetStatusInterface;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (!$request instanceof GetHumanStatus) {
            throw new InvalidArgumentException('Request not supported');
        }

        if (!$model->offsetExists('payment_state')) {
            $request->markNew();

            return;
        }

        Assertion::string($model['payment_state']);
        switch ($model['payment_state']) {
            case PaymentState::INITIAL:
            case PaymentState::SENT_FOR_PROCESSING:
            case PaymentState::WAITING_FOR_3DS_RESPONSE:
            case PaymentState::WAITING_FOR_SCA:
            case PaymentState::CONFIRMED_3DS:
                $request->markPending();
                return;
            case PaymentState::ABANDONED:
                $request->markExpired();
                return;
            case PaymentState::FAILED:
                $request->markFailed();
                return;
            case PaymentState::SETTLED:
                // EveryPay API do not make difference of Authorised and Captured
                // stages. In API both are indicated as "settled".
                // Therefore any payment status that is actually in Authorised stage,
                // is marked as Captured in our side.
                $request->markCaptured();
                return;
            case PaymentState::VOIDED:
                $request->markCanceled();
                return;
            case PaymentState::REFUNDED:
                $request->markRefunded();
                return;
            case PaymentState::CHARGE_BACKED:
                $request->markSuspended();
                return;
            default:
                $request->markUnknown();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }

}
