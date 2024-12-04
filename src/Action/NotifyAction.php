<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Sync;

class NotifyAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Notify $request
     *
     * @throws HttpResponse
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        if (!isset($httpRequest->query['payment_reference'])) {
            throw new HttpResponse('Missing payment_reference', 400);
        }
        $model['payment_reference'] = $httpRequest->query['payment_reference'];

        if (isset($httpRequest->query['event_name'])) {
            $model['event_name'] = $httpRequest->query['event_name'];
        }

        $this->gateway->execute(new Sync($model));

        $payment = $request->getFirstModel();
        if ($payment instanceof PaymentInterface) {
            $payment->setDetails($model);
        }
        $this->gateway->execute(new GetHumanStatus($payment));
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
