<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Authorize;

class AuthorizeAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Authorize $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        // Set payment type to one-off, if not specified. This is the most common use case
        if (!isset($model['_type'])) {
            $model['_type'] = PaymentType::ONE_OFF;
        }

        $token = $request->getToken();
        if ($token !== null && in_array($model['_type'], [PaymentType::ONE_OFF, PaymentType::CIT])) {
            $model['customer_url'] = $token->getAfterUrl();
        }

        $this->gateway->execute(new ApiAuthorize($model));
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request): bool
    {
        if (!$request instanceof Authorize) {
            return false;
        }

        $model = $request->getModel();
        if (!$model instanceof \ArrayAccess) {
            return false;
        }

        return true;
    }
}
