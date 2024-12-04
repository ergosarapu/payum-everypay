<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay;

use Assert\Assertion;
use ErgoSarapu\PayumEveryPay\Action\Api\OneOffAction;
use ErgoSarapu\PayumEveryPay\Action\Api\SyncAction;
use ErgoSarapu\PayumEveryPay\Action\AuthorizeAction;
use ErgoSarapu\PayumEveryPay\Action\CancelAction;
use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Action\ConvertPaymentAction;
use ErgoSarapu\PayumEveryPay\Action\NotifyAction;
use ErgoSarapu\PayumEveryPay\Action\RefundAction;
use ErgoSarapu\PayumEveryPay\Action\StatusAction;
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
        $config->defaults([
            'payum.factory_name' => 'everypay',
            'payum.factory_title' => 'EveryPay',
            'payum.action.capture' => new CaptureAction(),
            'payum.action.authorize' => new AuthorizeAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.sync' => new SyncAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),

            'payum.action.api.one_off' => new OneOffAction(),
        ]);

        if (false == $config['payum.api']) {
            $defaults = [
                'sandbox' => true,
                'token_agreement' => null,
                'token_consent_agreed' => false,

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
                Assertion::nullOrString($config['token_agreement']);
                Assertion::nullOrBoolean($config['token_consent_agreed']);
                Assertion::nullOrString($config['customer_url_replace_search']);
                Assertion::nullOrString($config['customer_url_replace_replacement']);

                return new Api(
                    client: $client,
                    messageFactory: $messageFactory,
                    username: $config['username'],
                    secret: $config['secret'],
                    accountName: $config['account_name'],
                    sandbox: $config['sandbox'],
                    tokenAgreement: $config['token_agreement'],
                    tokenConsentAgreed: $config['token_consent_agreed'],
                    customerUrlReplaceSearch: $config['customer_url_replace_search'],
                    customerUrlReplaceReplacement: $config['customer_url_replace_replacement']
                );
            };
        }
    }
}