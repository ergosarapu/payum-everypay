<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action\Api;

use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture;
use ErgoSarapu\PayumEveryPay\Request\Api\OneOff;
use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;

class CaptureAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param OneOff $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (false === $this->api instanceof Api) {
            throw new UnsupportedApiException('Incompatible api instance');
        }

        $response = $this->api->doCapture($model);
        Util::updateModel($model, $response);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(mixed $request)
    {
        if (!$request instanceof Capture) {
            return false;
        }

        if (!$request->getModel() instanceof \ArrayAccess) {
            return false;
        }

        $model = $request->getModel();
        if (!$model->offsetExists('_type')) {
            return false;
        }

        if (!$model->offsetExists('payment_reference')) {
            return false;
        }

        return true;
    }

}
