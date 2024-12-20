<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests\Helper;

use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Payum\Core\HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

trait NetworkMockTrait
{
    private function createHttpMessageFactory(): MessageFactory
    {
        return MessageFactoryDiscovery::find();
    }

    private function createResponseMock(?int $statusCode = 200, ?string $contents = null): ResponseInterface
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);
        $responseMock->expects($this->atLeastOnce())->method('getStatusCode')->willReturn($statusCode);
        $responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);
        $streamMock->expects($this->once())->method('getContents')->willReturn($contents);
        return $responseMock;
    }

    /**
     * @param array<RequestResponseHelper> $r
     */
    private function createClientMock(array $r): HttpClientInterface
    {
        $clientMock = $this->createMock(HttpClientInterface::class);

        $clientMock
            ->expects($this->exactly(count($r)))
            ->method('send')
            ->willReturnCallback(
                function (RequestInterface $request) use (&$r) {
                    $rrh = array_shift($r);
                    $this->assertNotNull($rrh);
                    $this->assertEquals($rrh->expectRequestPath, $request->getUri()->getPath());
                    $this->assertEquals($rrh->expectRequestMethod, $request->getMethod());

                    $body = json_decode($request->getBody()->getContents(), true);
                    if ($rrh->expectRequestBodyFieldsEqual !== null) {
                        $this->assertIsArray($body);
                        foreach ($rrh->expectRequestBodyFieldsEqual as $field => $value) {
                            $this->assertArrayHasKey($field, $body);
                            $this->assertEquals($value, $body[$field]);
                        }
                    }

                    if ($rrh->expectRequestBodyNotHasFields !== null) {
                        // $body = json_decode($request->getBody()->getContents(), true);
                        $this->assertIsArray($body);
                        foreach ($rrh->expectRequestBodyNotHasFields as $field) {
                            $this->assertArrayNotHasKey($field, $body);
                        }
                    }

                    if ($rrh->expectRequestBodyHasFields !== null) {
                        // $body = json_decode($request->getBody()->getContents(), true);
                        $this->assertIsArray($body);
                        foreach ($rrh->expectRequestBodyHasFields as $field) {
                            $this->assertArrayHasKey($field, $body);
                        }
                    }

                    $responseMock = $this->createResponseMock($rrh->responseStatusCode, $rrh->responseContents);
                    return $responseMock;
                }
            );
        return $clientMock;
    }
}
