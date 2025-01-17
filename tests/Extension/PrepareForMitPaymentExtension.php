<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Extension;

use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;

class PrepareForMitPaymentExtension implements ExtensionInterface
{
    /**
     * @param array<string,string> $tokenDetails
     */
    public function __construct(private array $tokenDetails, private string $tokenAgreement)
    {
    }

    public function onPreExecute(Context $context): void
    {
        $request = $context->getRequest();
        if (!($request instanceof Authorize || $request instanceof Capture)) {
            return;
        }

        $model = $request->getModel();
        if (!$model instanceof ArrayObject) {
            return;
        }

        // You may implement additional business logic specific to your use case
        // e.g. get the token from related subscription payment and attach it
        // to the model here

        $model['_type'] = PaymentType::MIT;
        $model['token_agreement'] = $this->tokenAgreement;
        $model['token_details'] = $this->tokenDetails;

        // Start of pseudo code

        // Get the payment instance
        // $payment = $request->getFirstModel();
        // if (!$payment instanceof Acme\MyPayment) {
        //     return;
        // }

        // Check if payment is for subscription
        // $subscription = $payment->getSubscription();
        // if ($subscription === null) {
        //     return;
        // }

        // Check if payment is the initial payment of subscription
        // $initialPayment = $subscription->getInitialPayment();
        // if ($initialPayment !== $payment) {
        //     return;
        // }

        // Get token_details from initial payment
        // $details = $initialPayment->getDetails();

        // if (!isset($details['token_agreement'])) {
        //     return;
        // }

        // if (!isset($details['cc_details'])) {
        //     return;
        // }

        // if (!isset($details['cc_details']['token'])) {
        //     return;
        // }

        // $model['token_agreement'] = $details['token_agreement'];
        // $model['token_detals'] = ['token' => $details['cc_details']['token']];

        // End of pseudo code
    }

    public function onExecute(Context $context): void
    {
    }

    public function onPostExecute(Context $context): void
    {
    }

}
