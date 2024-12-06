<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay;

use Payum\Core\Exception\Http\HttpException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiErrorException extends HttpException
{
    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return HttpException
     */
    public static function factory(RequestInterface $request, ResponseInterface $response)
    {
        $e = HttpException::factory($request, $response);

        $json = $response->getBody()->getContents();
        $error = json_decode($json, true);
        if (!is_array($error)) {
            return $e;
        }

        if (!array_key_exists('error', $error)) {
            return $e;
        }

        $error = $error['error'];
        $code = $error['code'];
        $message = $error['message'];

        $message = implode(PHP_EOL, [
            $e->getMessage(),
            '[error code] '.$code,
            '[error message] '.$message,
        ]);

        $e = new self($message, $code, $e);
        $e->setResponse($response);
        $e->setRequest($request);

        return $e;
    }
}
