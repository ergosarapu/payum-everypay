<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay;

use Assert\Assertion;
use ErgoSarapu\PayumEveryPay\Action\Api\CaptureAction as ApiCaptureAction;
use ErgoSarapu\PayumEveryPay\Action\Api\CitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Action\Api\MitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Action\Api\OneOffAction;
use ErgoSarapu\PayumEveryPay\Action\Api\SyncAction;
use ErgoSarapu\PayumEveryPay\Action\AuthorizeAction;
use ErgoSarapu\PayumEveryPay\Action\CancelAction;
use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Action\CapturePaymentAction;
use ErgoSarapu\PayumEveryPay\Action\ConvertPaymentAction;
use ErgoSarapu\PayumEveryPay\Action\NotifyAction;
use ErgoSarapu\PayumEveryPay\Action\RefundAction;
use ErgoSarapu\PayumEveryPay\Action\StatusAction;
use ErgoSarapu\PayumEveryPay\Action\ThrowOnNullTokenDetailsNotifyAction;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RuntimeException;
use Payum\Core\GatewayFactory;
use Payum\Core\HttpClientInterface;

class EveryPayGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config): void
    {

        // This will override the core CapturePaymentAction
        $config['payum.action.capture_payment'] = new CapturePaymentAction();

        $config->defaults([
            'payum.factory_name' => 'everypay',
            'payum.factory_title' => 'EveryPay',
            'payum.action.capture' => new CaptureAction(),
            'payum.action.authorize' => new AuthorizeAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.throw_on_null_token_details' => new ThrowOnNullTokenDetailsNotifyAction(),
            'payum.action.sync' => new SyncAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),

            'payum.action.api.one_off' => new OneOffAction(),
            'payum.action.api.capture' => new ApiCaptureAction(),
            'payum.action.api.authorize_mit' => new MitAndChargeAction(),
            'payum.action.api.authorize_cit' => new CitAndChargeAction(),

        ]);

        if (false == $config['payum.api']) {
            $defaults = [
                'sandbox' => true,
                // Appends method_source query parameter to payment_link (redirect url) enabling to
                // open specific payment method (e.g. card).
                // For available values you can inspect the payment dialog HTML source
                // by searching for 'method_source'.
                'payment_link_method_source' => null,

                // Config for modifying customer_url sent to EveryPay API.
                // It is not possible to use an IP address or localhost
                // as a customer URL, therefore this config becomes handy
                // for local testing by allowing substitution of certain
                // parts in an URL.
                // E.g. to substitute http://localhost/foo/bar with
                // https://foobar.ngrok-free.app/foo/bar specify
                // customer_url_replace_search='http://localhost' and
                // customer_url_replace_replacement='https://foobar.ngrok-free.app'
                'customer_url_replace_search' => '',
                'customer_url_replace_replacement' => '',
            ];
            $config['payum.default_options'] = $defaults;
            $config->defaults($defaults);

            $required = ['username', 'secret', 'account_name'];
            $config['payum.required_options'] = $required;

            $config['payum.api'] = function (ArrayObject $config) use ($required) {
                $config->validateNotEmpty($required);

                $client = $config['payum.http_client'];
                if (!$client instanceof HttpClientInterface) {
                    throw new RuntimeException('Invalid client instance');
                }

                $messageFactory = $config['httplug.message_factory'];
                if (!$messageFactory instanceof MessageFactory) {
                    throw new RuntimeException('Invalid message factory instance');
                }

                Assertion::string($config['username']);
                Assertion::string($config['secret']);
                Assertion::string($config['account_name']);
                Assertion::boolean($config['sandbox']);
                Assertion::nullOrString($config['payment_link_method_source']);
                Assertion::nullOrString($config['customer_url_replace_search']);
                Assertion::nullOrString($config['customer_url_replace_replacement']);

                return new Api(
                    client: $client,
                    messageFactory: $messageFactory,
                    username: $config['username'],
                    secret: $config['secret'],
                    accountName: $config['account_name'],
                    sandbox: $config['sandbox'],
                    methodSource: $config['payment_link_method_source'],
                    customerUrlReplaceSearch: $config['customer_url_replace_search'],
                    customerUrlReplaceReplacement: $config['customer_url_replace_replacement']
                );
            };
        }
    }
}
