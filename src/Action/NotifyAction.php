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
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Sync;

class NotifyAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    public const AUTO_CAPTURE_QUEUED = 'queued';
    public const AUTO_CAPTURE_TRIGGERED = 'triggered';

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
            throw new HttpResponse('payment_reference missing', 400);
        }
        $model['payment_reference'] = $httpRequest->query['payment_reference'];

        if (isset($httpRequest->query['event_name'])) {
            $model['event_name'] = $httpRequest->query['event_name'];
        }

        $this->gateway->execute(new Sync($model));

        $this->gateway->execute($getStatus = new GetHumanStatus($model));

        if (!$getStatus->isCaptured()) {
            return;
        }

        if ($model['_auto_capture_with_notify'] === self::AUTO_CAPTURE_QUEUED) {
            $model['_auto_capture_with_notify'] = self::AUTO_CAPTURE_TRIGGERED;

            $request = new Capture($request->getFirstModel());
            $request->setModel($model);
            $this->gateway->execute($request);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify
            && $request->getModel() instanceof \ArrayAccess
            && $request->getFirstModel() instanceof PaymentInterface
        ;
    }
}
