<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Extension;

use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;

class PrepareForCitPaymentExtension implements ExtensionInterface
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

        $model['_type'] = PaymentType::CIT;
        $model['token_agreement'] = $this->tokenAgreement;
        $model['token_details'] = $this->tokenDetails;

        // You may implement additional business logic specific to your use case
        // e.g. get the token from related user profile payment and attach it
        // to the model here

        // Start of pseudo code

        // Get the payment instance
        // $payment = $request->getFirstModel();
        // if (!$payment instanceof Acme\MyPayment) {
        //     return;
        // }

        // Get user for payment
        // $user = $payment->getUser();
        // if ($user === null) {
        //     return;
        // }

        // Check if user account has agreement payment
        // $agreementPayment = $user->getAgreementPayment();
        // if ($agreementPayment !== $payment) {
        //     return;
        // }

        // Get token_details from agreement payment
        // $details = $agreementPayment->getDetails();

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
