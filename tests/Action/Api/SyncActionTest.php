<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Action\Api;

use ErgoSarapu\PayumEveryPay\Action\Api\BaseApiAwareAction;
use ErgoSarapu\PayumEveryPay\Action\Api\SyncAction;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Sync;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncAction::class)]
class SyncActionTest extends TestCase
{
    public function testImplements(): void
    {
        $action = new SyncAction();

        $this->assertInstanceOf(BaseApiAwareAction::class, $action);
        $this->assertNotInstanceOf(GatewayInterface::class, $action);
    }

    public function testSupports(): void
    {
        $action = new SyncAction();

        $this->assertTrue($action->supports(new Sync([])));
        $this->assertFalse($action->supports(new Sync(null)));
        $this->assertFalse($action->supports(new Sync(null)));
    }
}
