<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Const;

class PaymentState
{
    public const INITIAL = 'initial';
    public const SETTLED = 'settled';
    public const FAILED = 'failed';
    public const ABANDONED = 'abandoned';
    public const VOIDED = 'voided';
    public const REFUNDED = 'refunded';
    public const SENT_FOR_PROCESSING = 'sent_for_processing';
    public const WAITING_FOR_3DS_RESPONSE = 'waiting_for_3ds_response';
    public const WAITING_FOR_SCA = 'waiting_for_sca';
    public const CONFIRMED_3DS = 'confirmed_3ds';
    public const CHARGE_BACKED = 'chargebacked';
}
