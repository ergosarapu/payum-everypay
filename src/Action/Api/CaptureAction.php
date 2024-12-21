<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action\Api;

use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\ApiErrorException;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture;
use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;

class CaptureAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (false === $this->api instanceof Api) {
            throw new UnsupportedApiException('Incompatible api instance');
        }

        try {
            $response = $this->api->doCapture($model);
            Util::updateModel($model, $response);
        } catch (ApiErrorException $e) {
            // Ignore Api Error in case capture has been already done.
            // This may happen when capture is attempted by simultaneous
            // processes, e.g:
            // 1. Api\OneOffAction succeeds
            // 2. Asynchronous NotifyAction (through callback URL) triggers Capture
            // 3. Customer URL Notify triggers Capture

            // 4032 - Can not be captured
            if ($e->getCode() !== 4032) {
                throw $e;
            }
        }
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
