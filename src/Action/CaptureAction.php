<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

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
use Payum\Core\Request\Authorize;
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

        if ($token !== null && in_array($model['_type'], [PaymentType::ONE_OFF, PaymentType::CIT])) {
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
        }

        // Execute authorize only if status is new.
        // This is because Capture may be called for already authorized payment
        // and in this case we do not want to trigger authorization any more.
        if ($getStatus->isNew()) {
            // Executing ApiAuthorize may throw Redirect (the case with OneOff and CIT payments).
            // Therefore we need to complete capture when Notify is triggered.
            // We will set a flag to make this happen.
            $model['_auto_capture_with_notify'] = NotifyAction::AUTO_CAPTURE_QUEUED;
            $this->gateway->execute(new ApiAuthorize($model));

            // In case we reached here it means we do not need to complete
            // the capture in Notify, clear the flag.
            unset($model['_auto_capture_with_notify']);
        }

        // Attempt capture
        $this->gateway->execute(new ApiCapture($model));
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
