<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay;

use Assert\Assert;
use Assert\Assertion;
use DateTime;
use ErgoSarapu\PayumEveryPay\Const\TokenAgreement;
use Http\Message\MessageFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\InvalidArgumentException;
use Payum\Core\HttpClientInterface;

class Api
{
    private const ALLOWED_CHARS = "/^[a-zA-Z0-9\/\-\?:\(\)\.,'\+]+$/";

    public function __construct(
        private HttpClientInterface $client,
        private MessageFactory $messageFactory,
        private string $username,
        private string $secret,
        private string $accountName,
        private bool $sandbox,
        private ?string $locale = null,
        private ?string $customerUrlReplaceSearch = null,
        private ?string $customerUrlReplaceReplacement = null
    ) {
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
            throw ApiErrorException::factory($request, $response);
        }

        $json = $response->getBody()->getContents();

        $result = json_decode($json, true);

        if (!is_array($result)) {
            throw new InvalidArgumentException('Response was not decoded into array');
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
        $fields = $this->prepareBaseFields($model);

        Assertion::string($model['customer_url']);
        Assertion::nullOrString($model['customer_ip']);
        Assert::thatNullOr($model['email'])->maxLength(255);
        Assert::thatNullOr($model['payment_description'])->maxLength(65)->regex(self::ALLOWED_CHARS);
        Assert::that($model['order_reference'])->string()->maxLength(120)->regex(self::ALLOWED_CHARS);

        $fields['order_reference'] = $model['order_reference'];
        $fields['locale'] = $this->locale;
        $fields['token_consent_agreed'] = $model['token_consent_agreed'];
        $fields['customer_url'] = $model['customer_url'];
        $fields['customer_ip'] = $model['customer_ip'];
        $fields['email'] = $model['email'];
        $fields['payment_description'] = $model['payment_description'];

        $fields['token_agreement'] = $model['token_agreement'];

        if ($model['request_token'] !== null) {
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
        $response = $this->doRequest('GET', sprintf('/%s', $model['payment_reference']), []);
        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function doCit(ArrayObject $model): array
    {
        $fields = $this->prepareCitFields($model);
        $response = $this->doRequest('POST', '/cit', $fields);
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareCitFields(ArrayObject $model): array
    {
        $fields = $this->prepareBaseFields($model);

        Assert::that($model['token_agreement'])->inArray([TokenAgreement::RECURRING, TokenAgreement::UNSCHEDULED]);
        Assert::that($model['customer_url'])->url();
        Assert::that($model['order_reference'])->string()->maxLength(120)->regex(self::ALLOWED_CHARS);

        $fields['order_reference'] = $model['order_reference'];
        $fields['token_agreement'] = $model['token_agreement'];
        $fields['customer_url'] = $model['customer_url'];

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    public function doMit(ArrayObject $model): array
    {
        $fields = $this->prepareMitFields($model);
        $response = $this->doRequest('POST', '/mit', $fields);
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareMitFields(ArrayObject $model): array
    {
        $fields = $this->prepareBaseFields($model);

        Assertion::notNull($model['token_agreement'], 'token_agreement is null');
        Assertion::string($model['token_agreement'], 'token_agreement is not string');
        Assert::that($model['token_agreement'])->inArray([TokenAgreement::RECURRING, TokenAgreement::UNSCHEDULED]);

        Assertion::string($model['merchant_ip'], 'merchant_ip is not string');
        Assertion::ip($model['merchant_ip'], message: sprintf("merchant_ip '%s' is not valid IP address", $model['merchant_ip']), flag:null);
        Assert::that($model['order_reference'])->string()->maxLength(120)->regex(self::ALLOWED_CHARS);

        $fields['order_reference'] = $model['order_reference'];
        $fields['token_agreement'] = $model['token_agreement'];
        $fields['merchant_ip'] = $model['merchant_ip'];

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    public function doCharge(ArrayObject $model): array
    {
        $fields = $this->prepareChargeFields($model);
        $response = $this->doRequest('POST', '/charge', $fields);
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareChargeFields(ArrayObject $model): array
    {
        $fields = $this->prepareBaseFields($model);

        Assertion::isArray($model['token_details']);
        Assertion::string($model['token_details']['token']);
        Assertion::string($model['payment_reference']);

        $fields['token_details'] = ['token' => $model['token_details']['token']];
        $fields['payment_reference'] = $model['payment_reference'];

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    public function doCapture(ArrayObject $model): array
    {
        $fields = $this->prepareCaptureFields($model);
        $response = $this->doRequest('POST', '/capture', $fields);
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareCaptureFields(ArrayObject $model): array
    {
        $fields = $this->prepareBaseFields($model);

        Assertion::string($model['payment_reference']);
        $fields['payment_reference'] = $model['payment_reference'];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareBaseFields(ArrayObject $model): array
    {
        Assertion::numeric($model['amount']);

        $fields = [
            'account_name' => $this->accountName,
            'nonce' => uniqid(),
            'timestamp' => (new DateTime())->format('c'), // ISO 8601 date
            'amount' => $model['amount']
        ];

        return $fields;
    }
}
