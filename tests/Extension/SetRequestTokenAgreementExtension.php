<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Extension;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;

/**
 * Extension which adds specified 'token_agreement' value and 'request_token=true' to model.
 */
class SetRequestTokenAgreementExtension implements ExtensionInterface
{
    public function __construct(private string $tokenAgreement, private bool $tokenConsentAgreed)
    {
    }

    public function onPreExecute(Context $context): void
    {
    }

    public function onExecute(Context $context): void
    {
        $request = $context->getRequest();
        if (!($request instanceof Capture || $request instanceof Authorize)) {
            return;
        }

        $model = $request->getModel();
        if (!$model instanceof ArrayObject) {
            return;
        }

        // You may implement additional business logic specific to your use case

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
        // if ($subscription->getInitialPayment() !== $payment) {
        //     return;
        // }

        // End of pseudo code

        // Set model properties to request token
        $model['token_agreement'] = $this->tokenAgreement;
        $model['request_token'] = true;
        $model['token_consent_agreed'] = $this->tokenConsentAgreed;
    }

    public function onPostExecute(Context $context): void
    {
    }

}
