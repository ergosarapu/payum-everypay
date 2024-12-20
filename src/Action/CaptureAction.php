<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\ApiErrorException;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture as ApiCapture;
use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetHumanStatus;

class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
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

        if ($token !== null) {
            $model['customer_url'] = $token->getAfterUrl();
        }

        $this->gateway->execute($getStatus = new GetHumanStatus($model));

        if (!$getStatus->isNew()) {
            // Force convert payment if it is not new, this ensures that
            // payment data which may have changed gets reflected in model.
            // E.g. Capture amount may be less than the initial Authorize amount
            $this->gateway->execute($convert = new Convert($request->getFirstModel(), 'array', $request->getToken()));
            $result = ArrayObject::ensureArrayObject($convert->getResult());
            Util::updateModel($model, $result->toUnsafeArray());
        } else {
            // Executing ApiAuthorize may throw Redirect (the case with OneOff and CIT payments).
            // Therefore we need to complete capture when Notify is triggered.
            // We will set a flag to make this happen.
            $model['_auto_capture_with_notify'] = NotifyAction::AUTO_CAPTURE_QUEUED;
            $this->gateway->execute(new ApiAuthorize($model));

            // In case we reached here it means we do not need to complete
            // the capture in Notify, clear the flag.
            unset($model['_auto_capture_with_notify']);
        }

        $this->gateway->execute($getStatus = new GetHumanStatus($model));

        // Capture is done only when payment is settled (Captured)
        if ($getStatus->isCaptured()) {
            try {
                $this->gateway->execute(new ApiCapture($model));
            } catch (ApiErrorException $e) {
                // Ignore Api Error in case capture has been already done.
                // This may happen when capture is attempted by simultaneous
                // processes, e.g:
                // 1. Api\OneOffAction succeeds
                // 2. Asynchronous (through callback URL) NotifyAction triggers Capture
                // 3. Customer URL Notify triggers Capture

                // 4032 - Can not be captured
                if ($e->getCode() !== 4032) {
                    throw $e;
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {

        if (!$request instanceof Capture) {
            return false;
        }

        $model = $request->getModel();
        if (!$model instanceof \ArrayAccess) {
            return false;
        }

        if (!$request->getFirstModel() instanceof PaymentInterface) {
            return false;
        }

        return true;
    }
}
