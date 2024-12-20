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

class MitAndChargeAction extends BaseApiAwareAction
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

        // Should we use public ip address here?
        // Following method may not be universal enough
        $hostname = gethostname();
        if ($hostname !== false) {
            $host = gethostbyname($hostname);
            $model['merchant_ip'] = $host;
        }

        $response = $this->api->doMit($model);
        Util::updateModel($model, $response);

        $response = $this->api->doCharge($model);
        Util::updateModel($model, $response);
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

        if ($model['_type'] !== PaymentType::MIT) {
            return false;
        }

        return true;
    }

}
