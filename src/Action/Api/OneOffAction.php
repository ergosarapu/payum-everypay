<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action\Api;

use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize;
use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;

class OneOffAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Authorize $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (false === $this->api instanceof Api) {
            throw new UnsupportedApiException('Incompatible api instance');
        }

        $this->gateway->execute($getStatus = new GetHumanStatus($model));
        if ($getStatus->isPending() && is_string($model['payment_link'])) {
            throw new HttpRedirect($model['payment_link']);
        }

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        $model['customer_ip'] = $httpRequest->clientIp;

        $response = $this->api->doOneOff($model);
        Util::updateModel($model, $response);

        if (is_string($model['payment_link'])) {
            throw new HttpRedirect($model['payment_link']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports(mixed $request)
    {
        if (!$request instanceof Authorize) {
            return false;
        }

        $model = $request->getModel();
        if (!$model instanceof \ArrayAccess) {
            return false;
        }

        if (!$model->offsetExists('_type')) {
            return false;
        }

        if ($model['_type'] !== PaymentType::ONE_OFF) {
            return false;
        }

        return true;
    }

}
