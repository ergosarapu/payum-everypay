<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests;

use Assert\InvalidArgumentException;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Tests\Helper\NetworkMockTrait;
use ErgoSarapu\PayumEveryPay\Tests\Helper\RequestResponseHelper as R;
use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Api::class)]
class ApiTest extends TestCase
{
    use NetworkMockTrait;

    public function testReturnSandboxEndpointIfSandboxSetTrueInConstructor(): void
    {
        $api = new Api(
            client: $this->createClientMock([]),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
        );
        $this->assertSame('https://igw-demo.every-pay.com/api/v4/payments', $api->getApiEndpoint());
    }

    public function testReturnLiveEndpointIfSandboxSetFalseInConstructor(): void
    {
        $api = new Api(
            client: $this->createClientMock([]),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: false,
        );
        $this->assertSame('https://pay.every-pay.eu/api/v4/payments', $api->getApiEndpoint());
    }

    public function testRequestOneOff(): void
    {
        $clientMock = $this->createClientMock([
            new R(
                expectRequestPath: '/api/v4/payments/oneoff',
                expectRequestMethod: 'POST',
                expectRequestBodyFieldsEqual: [
                    'account_name' => 'test_account_name',
                    'token_consent_agreed' => true,
                    'locale' => 'en',
                    'amount' => 1.23,
                    'order_reference' => "a-zA-Z0-9/-?:().,'+",
                    'customer_ip' => '127.0.0.1',
                    'customer_url' => 'http://localhost',
                    'email' => 'example@example.com',
                    'payment_description' => "a-zA-Z0-9/-?:().,'+",
                    'api_username' => 'test_username',
                ],
                expectRequestBodyHasFields: ['nonce', 'timestamp'],
                responseStatusCode: 200,
                responseContents: '{"foo": "bar"}',
            )
        ]);

        $api = new Api(
            client: $clientMock,
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
            locale: 'en'
        );

        $response = $api->doOneOff(new ArrayObject([
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'payment_description' => "a-zA-Z0-9/-?:().,'+",
            'amount' => 1.23,
            'customer_ip' => '127.0.0.1',
            'customer_url' => 'http://localhost',
            'email' => 'example@example.com',
            'token_consent_agreed' => true,
        ]));

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testModifyCustomerUrl(): void
    {
        $clientMock = $this->createClientMock([
            new R(
                expectRequestPath: '/api/v4/payments/oneoff',
                expectRequestMethod: 'POST',
                expectRequestBodyFieldsEqual:[
                    'customer_url' => 'https://example.com'
                ],
                responseStatusCode: 200,
                responseContents: '{"foo": "bar"}',
            )
        ]);

        $api = new Api(
            client: $clientMock,
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
            customerUrlReplaceSearch: 'http://localhost',
            customerUrlReplaceReplacement: 'https://example.com',
        );

        $response = $api->doOneOff(new ArrayObject([
            'amount' => 1.23,
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'customer_url' => 'http://localhost',
            'email' => 'example@example.com',
        ]));

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testModifyOneOffPaymentLink(): void
    {
        $clientMock = $this->createClientMock([
            new R(
                expectRequestPath: '/api/v4/payments/oneoff',
                expectRequestMethod: 'POST',
                expectRequestBodyFieldsEqual:[
                    'customer_url' => 'https://example.com'
                ],
                responseStatusCode: 200,
                responseContents: '{"payment_link": "https://igw-demo.every-pay.com/foo/bar"}',
            )
        ]);

        $api = new Api(
            client: $clientMock,
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
            methodSource: 'card',
        );

        $response = $api->doOneOff(new ArrayObject([
            'amount' => 1.23,
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'customer_url' => 'https://example.com',
            'email' => 'example@example.com',
        ]));

        $this->assertEquals(['payment_link' => 'https://igw-demo.every-pay.com/foo/bar?method_source=card'], $response);
    }

    public function testValidateOrderReference(): void
    {
        $api = new Api(
            client: $this->createClientMock([]),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
        );

        $this->expectException(InvalidArgumentException::class);
        $api->doOneOff(new ArrayObject([
            'order_reference' => ' ',
        ]));
    }

    public function testValidateDescription(): void
    {
        $api = new Api(
            client: $this->createClientMock([]),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
        );

        $this->expectException(InvalidArgumentException::class);
        $api->doOneOff(new ArrayObject([
            'payment_description' => ' ',
        ]));
    }
}
