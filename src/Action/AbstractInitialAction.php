<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Generic;

abstract class AbstractInitialAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    protected function initializeModel(Generic $request): ArrayObject
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

        return $model;
    }
}
