<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action\Api;

use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Request\Api\OneOff;
use InvalidArgumentException;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;

class OneOffAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param OneOff $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (false === $this->api instanceof Api) {
            throw new UnsupportedApiException('Incompatible api instance');
        }

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        $model['customer_ip'] = $httpRequest->clientIp;

        $response = $this->api->doOneOff($model);

        if (!is_string($response['payment_link'])) {
            throw new InvalidArgumentException('payment_link not a string');
        }
        throw new HttpRedirect($response['payment_link']);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(mixed $request)
    {
        return
            $request instanceof Oneoff &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }

}
