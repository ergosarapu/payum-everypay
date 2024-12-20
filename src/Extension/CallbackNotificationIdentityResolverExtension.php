<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Extension;

use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Model\DetailsAggregateInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Payum\Core\Security\TokenInterface;
use Payum\Core\Storage\StorageInterface;

/**
 * An extension to identify Payment model when Notify request is triggered
 * as a result of a call to EveryPay Callback Notification URL.
 * @package ErgoSarapu\PayumEveryPay\Extension
 */
class CallbackNotificationIdentityResolverExtension implements ExtensionInterface
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function onPreExecute(Context $context): void
    {
        $request = $context->getRequest();

        if (!$request instanceof Notify) {
            return;
        }

        $model = $request->getModel();
        if (!$model instanceof TokenInterface) {
            return;
        }

        /** @var mixed $details */
        $details = $model->getDetails();
        if ($details !== null) {
            return;
        }

        // We have Notify request with model as Token and without Identity details
        // Start finding out the Identity matching the order_reference and payment_reference

        // Validate required query parameters are set
        $context->getGateway()->execute($httpRequest = new GetHttpRequest());
        if (!isset($httpRequest->query['order_reference'])) {
            throw new HttpResponse('order_reference missing', 400);
        }

        if (!isset($httpRequest->query['payment_reference'])) {
            throw new HttpResponse('payment_reference missing', 400);
        }

        $orderReference = $httpRequest->query['order_reference'];
        $paymentReference = $httpRequest->query['payment_reference'];

        // Find payments by order reference
        /** @var array<DetailsAggregateInterface> $results */
        $results = $this->storage->findBy(['number' => $orderReference]);

        // Loop through all results to see which of them matches with the payment_reference
        foreach ($results as $payment) {

            $details = (array)$payment->getDetails();
            if (!isset($details['payment_reference'])) {
                continue;
            }

            if ($details['payment_reference'] !== $paymentReference) {
                continue;
            }

            // Identify the model and set it back to request
            // This Identity will be used by Payum storage extension to load the payment
            $identity = $this->storage->identify($payment);
            $request->setModel($identity);
            return;
        }

        throw new HttpResponse('payment not found', 404);
    }

    public function onExecute(Context $context): void
    {
    }

    public function onPostExecute(Context $context): void
    {
    }

}
