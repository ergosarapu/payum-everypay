# Payum EveryPay

The [Payum](https://github.com/Payum/Payum) extension for [EveryPay](https://support.every-pay.com/api-documentation/) payment gateway integration.

## Installation

Install using Composer :

```bash
composer require ergosarapu/payum-everypay
```

### Configure EveryPay callback notifications

1. Create notify token without payment model identity. If using Symfony, this can be done as follows:
    ```bash
    php bin/console payum:security:create-notify-token everypay
    ```
2. Configure notification callback in EveryPay merchant portal with the generated URL
3. Register [CallbackNotificationIdentityResolverExtension](./src/Extension/CallbackNotificationIdentityResolverExtension.php) with the gateway. This extension resolves the payment model identity based on the `payment_reference`.

## Supported Operations

### Authorize

Authorize initiates the payment, but does not capture it. Note that depending on `capture delay` setting on the EveryPay account used, the authorization may result the payment to be still captured immediately.

### Capture

Initiates payment to capture immediately. Also captures previously authorized payment.

### Token Agreements for MIT/CIT payments

To request token for later use in MIT/CIT payments, you may create and register extension in your gateway to set `token_agreement`, `request_token`, `token_consent_agreed` values.

See example extension here: [SetRequestTokenAgreementExtension](tests/Extension/SetRequestTokenAgreementExtension.php)

### MIT/CIT

To perform CIT or MIT payment, you may create and register extension in your gateway to set `_type`, `token_agreement`, `token_details` values.

See example extension for CIT: [PrepareForCitPaymentExtension](tests/Extension/PrepareForCitPaymentExtension.php)

See example extension for MIT: [PrepareForMitPaymentExtension](tests/Extension/PrepareForMitPaymentExtension.php)

### Cancel

TODO: Not yet supported