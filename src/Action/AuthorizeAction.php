<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Action;

use ErgoSarapu\PayumEveryPay\Request\Api\Authorize as ApiAuthorize;
use Payum\Core\Request\Authorize;

class AuthorizeAction extends AbstractInitialAction
{
    /**
     * {@inheritDoc}
     *
     * @param Authorize $request
     */
    public function execute($request): void
    {
        $model = $this->initializeModel($request);
        $this->gateway->execute(new ApiAuthorize($model));
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request): bool
    {
        if (!$request instanceof Authorize) {
            return false;
        }

        $model = $request->getModel();
        if (!$model instanceof \ArrayAccess) {
            return false;
        }

        return true;
    }
}
