<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests;

use Assert\InvalidArgumentException;
use ErgoSarapu\PayumEveryPay\Api;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\HttpClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(Api::class)]
class ApiTest extends TestCase
{
    public function testShouldReturnSandboxEndpointIfSandboxSetTrueInConstructor(): void
    {
        $api = new Api(
            client: $this->createMock(HttpClientInterface::class),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
        );
        $this->assertSame('https://igw-demo.every-pay.com/api/v4/payments', $api->getApiEndpoint());
    }

    public function testShouldReturnLiveEndpointIfSandboxSetFalseInConstructor(): void
    {
        $api = new Api(
            client: $this->createMock(HttpClientInterface::class),
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: false,
        );
        $this->assertSame('https://pay.every-pay.eu/api/v4/payments', $api->getApiEndpoint());
    }

    public function testShouldFillAndRequestOneOff(): void
    {
        $clientMock = $this->createMock(HttpClientInterface::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $streamMock->expects($this->once())->method('getContents')->willReturn('{"foo": "bar"}');
        $responseMock->expects($this->atLeastOnce())->method('getStatusCode')->willReturn(200);
        $responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);

        $api = new Api(
            client: $clientMock,
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
            locale: 'en',
            tokenConsentAgreed: true
        );

        $clientMock->expects($this->once())->method('send')->willReturnCallback(
            function (RequestInterface $request) use ($responseMock) {
                $actual = json_decode($request->getBody()->getContents(), true);

                $this->assertIsArray($actual);
                $this->assertEquals([
                    'account_name' => 'test_account_name',
                    'token_consent_agreed' => true,
                    'locale' => 'en',
                    'nonce' => $actual['nonce'],
                    'timestamp' => $actual['timestamp'],
                    'amount' => 1.23,
                    'order_reference' => "a-zA-Z0-9/-?:().,'+",
                    'customer_ip' => '127.0.0.1',
                    'customer_url' => 'http://localhost',
                    'email' => 'example@example.com',
                    'payment_description' => "a-zA-Z0-9/-?:().,'+",
                    'api_username' => 'test_username',
                ], $actual);

                return $responseMock;
            }
        );

        $response = $api->doOneOff(new ArrayObject([
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'payment_description' => "a-zA-Z0-9/-?:().,'+",
            'amount' => 1.23,
            'customer_ip' => '127.0.0.1',
            'customer_url' => 'http://localhost',
            'email' => 'example@example.com',
        ]));

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testShouldFillRequestTokenField(): void
    {
        $clientMock = $this->createMock(HttpClientInterface::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $streamMock->expects($this->once())->method('getContents')->willReturn('{"foo": "bar"}');
        $responseMock->expects($this->atLeastOnce())->method('getStatusCode')->willReturn(200);
        $responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);

        $api = new Api(
            client: $clientMock,
            messageFactory: $this->createHttpMessageFactory(),
            username: 'test_username',
            secret: 'test_secret',
            accountName: 'test_account_name',
            sandbox: true,
            tokenAgreement: 'unscheduled',
        );

        $clientMock->expects($this->once())->method('send')->willReturnCallback(
            function (RequestInterface $request) use ($responseMock) {
                $actual = json_decode($request->getBody()->getContents(), true);

                $this->assertIsArray($actual);
                $this->assertEquals([
                    'account_name' => 'test_account_name',
                    'nonce' => $actual['nonce'],
                    'timestamp' => $actual['timestamp'],
                    'amount' => 1.23,
                    'order_reference' => "a-zA-Z0-9/-?:().,'+",
                    'customer_url' => 'http://localhost',
                    'email' => 'example@example.com',
                    'api_username' => 'test_username',
                    'token_agreement' => 'unscheduled',
                    'request_token' => true
                ], $actual);

                return $responseMock;
            }
        );

        $response = $api->doOneOff(new ArrayObject([
            'amount' => 1.23,
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'customer_url' => 'http://localhost',
            'email' => 'example@example.com',
        ]));

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testShouldModifyCustomerUrl(): void
    {
        $clientMock = $this->createMock(HttpClientInterface::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $streamMock->expects($this->once())->method('getContents')->willReturn('{"foo": "bar"}');
        $responseMock->expects($this->atLeastOnce())->method('getStatusCode')->willReturn(200);
        $responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);

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

        $clientMock->expects($this->once())->method('send')->willReturnCallback(
            function (RequestInterface $request) use ($responseMock) {
                $actual = json_decode($request->getBody()->getContents(), true);

                $this->assertIsArray($actual);
                $this->assertEquals([
                    'account_name' => 'test_account_name',
                    'nonce' => $actual['nonce'],
                    'timestamp' => $actual['timestamp'],
                    'amount' => 1.23,
                    'order_reference' => "a-zA-Z0-9/-?:().,'+",
                    'customer_url' => 'https://example.com',
                    'email' => 'example@example.com',
                    'api_username' => 'test_username',
                ], $actual);

                return $responseMock;
            }
        );

        $response = $api->doOneOff(new ArrayObject([
            'amount' => 1.23,
            'order_reference' => "a-zA-Z0-9/-?:().,'+",
            'customer_url' => 'http://localhost',
            'email' => 'example@example.com',
        ]));

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testShouldValidateOrderReference(): void
    {
        $api = new Api(
            client: $this->createMock(HttpClientInterface::class),
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

    public function testShouldValidateDescription(): void
    {
        $api = new Api(
            client: $this->createMock(HttpClientInterface::class),
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


    private function createHttpMessageFactory(): MessageFactory
    {
        return MessageFactoryDiscovery::find();
    }
}
