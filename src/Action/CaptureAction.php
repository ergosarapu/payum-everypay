<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use ErgoSarapu\PayumEveryPay\Request\Api\Capture as ApiCapture;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHumanStatus;

class CaptureAction extends AbstractInitialAction
{
    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        $model = $this->initializeModel($request);

        $this->gateway->execute($status = new GetHumanStatus($model));

        // Execute authorize only if status is new.
        // This is because Capture may be called for already authorized payment
        // and in this case we do not want to trigger authorization any more.
        if ($status->isNew()) {
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

        return true;
    }
}
