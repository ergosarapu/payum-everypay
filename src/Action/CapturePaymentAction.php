<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Util\Util;
use Payum\Core\Action\CapturePaymentAction as CoreCapturePaymentAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Convert;

class CapturePaymentAction extends CoreCapturePaymentAction
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

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        if (empty($payment->getDetails())) {
            $details = ArrayObject::ensureArrayObject([]);
        } else {
            $details = ArrayObject::ensureArrayObject($payment->getDetails());
        }

        // Always convert payment, this ensures that
        // payment data which may have changed gets reflected in model.
        // E.g. Capture amount may be less than the initial Authorize amount
        $this->gateway->execute($convert = new Convert($payment, 'array', $request->getToken()));
        $result = ArrayObject::ensureArrayObject($convert->getResult());
        Util::updateModel($details, $result->toUnsafeArray());

        $request->setModel($details);
        try {
            $this->gateway->execute($request);
        } finally {
            $payment->setDetails($details);
        }
    }
}
