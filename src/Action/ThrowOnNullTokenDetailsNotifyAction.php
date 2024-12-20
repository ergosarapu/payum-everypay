<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Extension\CallbackNotificationIdentityResolverExtension;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Notify;

class ThrowOnNullTokenDetailsNotifyAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Notify $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        throw new LogicException('Caught Notify request with model set to null. This probably means you haven\'t registered identity resolver extension. See ' . CallbackNotificationIdentityResolverExtension::class);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        if (!$request instanceof Notify) {
            return false;
        }

        if ($request->getModel() !== null) {
            return false;
        }

        if ($request->getFirstModel() !== null) {
            return false;
        }

        return true;
    }
}
