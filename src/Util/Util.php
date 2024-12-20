<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Util;

use Payum\Core\Bridge\Spl\ArrayObject;

class Util
{
    /**
     *
     * @param ArrayObject<string,mixed> $model
     * @param array<string,mixed>       $update
     */
    public static function updateModel(ArrayObject $model, array $update): void
    {
        $synced = array_merge($model->toUnsafeArray(), $update);
        $model->exchangeArray($synced);
    }
}
