<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action\Api;

use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize;
use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetHumanStatus;

class CitAndChargeAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Authorize $request
     * @throws HttpRedirect
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (false === $this->api instanceof Api) {
            throw new UnsupportedApiException('Incompatible api instance');
        }

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        $model['customer_ip'] = $httpRequest->clientIp;

        $model->validatedKeysSet(['customer_url']);

        $this->gateway->execute($status = new GetHumanStatus($model));

        // Execute doCit only in case the status is new. If the status
        // is not new, this means doCit may have already been called
        // (e.g. the previous payment attempt succeeded doCit, but failed
        // in doCharge and the upstream process is retrying payment).
        if ($status->isNew()) {
            $response = $this->api->doCit($model);
            Util::updateModel($model, $response);
        }

        $response = $this->api->doCharge($model);
        Util::updateModel($model, $response);

        if (!is_string($model['payment_link'])) {
            throw new InvalidArgumentException('payment_link not a string');
        }
        throw new HttpRedirect($model['payment_link']);
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

        if ($model['_type'] !== PaymentType::CIT) {
            return false;
        }

        return true;
    }

}
