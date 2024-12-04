<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use Assert\Assertion;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\Exception\RequestNotSupportedException;
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

        if (!$model->offsetExists('payment_state')) {
            $request->markNew();

            return;
        }

        Assertion::string($model['payment_state']);
        switch ($model['payment_state']) {
            case 'initial':
                $request->markPending();
                return;
            case 'abandoned':
                $request->markExpired();
                return;
            case 'failed':
                $request->markFailed();
                return;
            case 'settled':
                $request->markCaptured();
                return;
            case 'authorized':
                $request->markAuthorized();
                return;
            case 'voided':
                $request->markCanceled();
                return;
            case 'refunded':
                $request->markRefunded();
                return;
        }

        throw new InvalidArgumentException(sprintf("Unknown payment_state '%s'", $model['payment_state']));
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
