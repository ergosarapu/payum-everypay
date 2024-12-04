<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay;

use Assert\Assert;
use Assert\Assertion;
use DateTime;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\HttpClientInterface;

class Api
{
    private const ALLOWED_CHARS = "/^[a-zA-Z0-9\/\-\?:\(\)\.,'\+]+$/";
    private const ALLOWED_TOKEN_AGREEMENT = "/^(unscheduled|recurring)+$/";

    public function __construct(
        private HttpClientInterface $client,
        private MessageFactory $messageFactory,
        private string $username,
        private string $secret,
        private string $accountName,
        private bool $sandbox,
        private ?string $locale = null,
        private ?string $tokenAgreement = null,
        private ?bool $tokenConsentAgreed = null,
        private ?string $customerUrlReplaceSearch = null,
        private ?string $customerUrlReplaceReplacement = null,
    ) {
        Assert::thatNullOr($this->tokenAgreement)->regex(self::ALLOWED_TOKEN_AGREEMENT);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<mixed>
     */
    public function doRequest(string $method, string $path, array $fields): array
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->username.":".$this->secret),
            'content-type' => 'application/json',
        ];

        $fields['api_username'] = $this->username;
        $query = '';
        $body = null;
        if ($method === 'GET') {
            $query = '?'.http_build_query($fields);
        } else {
            $body = json_encode($fields);
            if ($body === false) {
                $body = null;
            }
        }

        $request = $this->messageFactory->createRequest($method, $this->getApiEndpoint() . $path . $query, $headers, $body);

        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        $json = $response->getBody()->getContents();

        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new InvalidArgumentException('Response was nod decoded into array');
        }
        return $result;
    }

    public function getApiEndpoint(): string
    {
        return $this->sandbox ? 'https://igw-demo.every-pay.com/api/v4/payments' : 'https://pay.every-pay.eu/api/v4/payments';
    }

    /**
     *
     * @param array<string, mixed> &$fields
     */
    private function modifyCustomerUrl(array &$fields): void
    {
        $url = $fields['customer_url'];
        if (!is_string($url)) {
            throw new InvalidArgumentException('Url not a string');
        }

        $search = $this->customerUrlReplaceSearch;
        $replace = $this->customerUrlReplaceReplacement;

        if ($search === null) {
            return;
        }
        if ($replace === null) {
            return;
        }
        $newUrl = str_replace($search, $replace, $url);
        $fields['customer_url'] = $newUrl;
    }

    /**
     * @return array<mixed>
     */
    public function doOneOff(ArrayObject $model): array
    {
        $fields = $this->prepareOneOffFields($model);
        $response = $this->doRequest('POST', '/oneoff', $fields);
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareOneOffFields(ArrayObject $model): array
    {
        Assertion::numeric($model['amount']);
        Assert::that($model['order_reference'])->string()->maxLength(120)->regex(self::ALLOWED_CHARS);
        Assertion::string($model['customer_url']);
        Assertion::nullOrString($model['customer_ip']);
        Assert::thatNullOr($model['email'])->maxLength(255);
        Assert::thatNullOr($model['payment_description'])->maxLength(65)->regex(self::ALLOWED_CHARS);

        $fields = [
            // Fill from options
            'account_name' => $this->accountName, // $this->validate($this->options, 'account_name'),
            'token_consent_agreed' => $this->tokenConsentAgreed, // $this->validate($this->options, 'token_consent_agreed', required: false), // $this->options['token_consent_agreed'] ?? false,
            'locale' => $this->locale, // $this->validate($this->options, 'locale', required: false), // $this->options['locale'] ?? null,

            // Fill by generating values
            'nonce' => uniqid(),
            'timestamp' => (new DateTime())->format('c'), // ISO 8601 date

            // Fill required fields from model
            'amount' => $model['amount'],
            'order_reference' => $model['order_reference'], //Validator::validateModelStringRequired($model, 'order_reference', maxChars: 120, pregMatch: self::ALLOWED_CHARS),
            'customer_url' => $model['customer_url'],

            // Fill optional fields from model
            'customer_ip' => $model['customer_ip'],
            'email' => $model['email'],
            'payment_description' => $model['payment_description'],
        ];

        if ($this->tokenAgreement !== null) {
            $fields['token_agreement'] = $this->tokenAgreement;
            $fields['request_token'] = true;
        }

        // Filter out null values
        $fields = array_filter($fields, function (mixed $value) {
            return $value !== null;
        });

        // Mofify customer URL
        $this->modifyCustomerUrl($fields);

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    public function doGetPaymentStatus(ArrayObject $model): array
    {
        Assertion::string($model['payment_reference']);
        return $this->doRequest('GET', sprintf('/%s', $model['payment_reference']), []);
    }
}
