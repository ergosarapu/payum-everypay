<?php

declare(strict_types=1);

namespace ErgoSarapu\PayumEveryPay\Tests;

use ErgoSarapu\PayumEveryPay\Action\Api\CaptureAction as ApiCaptureAction;
use ErgoSarapu\PayumEveryPay\Action\Api\CitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Action\Api\MitAndChargeAction;
use ErgoSarapu\PayumEveryPay\Action\Api\OneOffAction;
use ErgoSarapu\PayumEveryPay\Action\AuthorizeAction;
use ErgoSarapu\PayumEveryPay\Action\CaptureAction;
use ErgoSarapu\PayumEveryPay\Action\ConvertPaymentAction;
use ErgoSarapu\PayumEveryPay\Action\NotifyAction;
use ErgoSarapu\PayumEveryPay\Action\StatusAction;
use ErgoSarapu\PayumEveryPay\Api;
use ErgoSarapu\PayumEveryPay\Const\PaymentState;
use ErgoSarapu\PayumEveryPay\Const\PaymentType;
use ErgoSarapu\PayumEveryPay\Const\TokenAgreement;
use ErgoSarapu\PayumEveryPay\EveryPayGatewayFactory;
use ErgoSarapu\PayumEveryPay\Extension\CallbackNotificationIdentityResolverExtension;
use ErgoSarapu\PayumEveryPay\Tests\Extension\PrepareForCitPaymentExtension;
use ErgoSarapu\PayumEveryPay\Tests\Extension\PrepareForMitPaymentExtension;
use ErgoSarapu\PayumEveryPay\Tests\Extension\SetRequestTokenAgreementExtension;
use ErgoSarapu\PayumEveryPay\Tests\Helper\NetworkMockTrait;
use ErgoSarapu\PayumEveryPay\Tests\Helper\RequestResponseHelper as R;
use Generator;
use Http\Message\MessageFactory;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\HttpClientInterface;
use Payum\Core\Model\Identity;
use Payum\Core\Model\ModelAggregateInterface;
use Payum\Core\Model\ModelAwareInterface;
use Payum\Core\Model\Payment;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Generic;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Payum\Core\Security\TokenInterface;
use Payum\Core\Storage\IdentityInterface;
use Payum\Core\Storage\StorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CaptureAction::class)]
#[CoversClass(AuthorizeAction::class)]
#[CoversClass(StatusAction::class)]
#[CoversClass(ConvertPaymentAction::class)]
#[CoversClass(OneOffAction::class)]
#[CoversClass(ApiCaptureAction::class)]
#[CoversClass(MitAndChargeAction::class)]
#[CoversClass(CitAndChargeAction::class)]
#[CoversClass(Api::class)]
class GatewayFunctionalTest extends TestCase
{
    use NetworkMockTrait;

    #[DataProvider('newPaymentRequestExpectations')]
    public function testNewPaymentAuthorizeOrCaptureTriggersApiOneOff(string $requestClass, ?string $expectAutoCapture): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/oneoff',
                    expectRequestMethod:'POST',
                    expectRequestBodyNotHasFields: ['request_token', 'token_agreement', 'token_consent_agreed'],
                    responseStatusCode: 200,
                    responseContents:'{"payment_link": "https://example.com"}',
                )
            ]
        );

        // Create gateway
        $gateway = $this->createTestGateway($clientMock, $messageFactory);

        // Create payment for Capture
        $payment = new Payment();
        $payment->setNumber('123');
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getAfterUrl')->willReturn('https://example.com');

        /** @var Generic $request */
        $request = new $requestClass($token);
        // $request = new Capture($token);
        $request->setModel($payment);

        $exception = null;
        try {
            $gateway->execute($request);
        } catch (HttpRedirect $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertEquals('https://example.com', $exception->getUrl());
        $this->assertEquals(302, $exception->getStatusCode());

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals(PaymentType::ONE_OFF, $model['_type']);
        $this->assertEquals($expectAutoCapture, $model['_auto_capture_with_notify']);
    }

    #[DataProvider('newPaymentRequestExpectations')]
    public function testNewPaymentAuthorizeOrCaptureWithRequestTokenAgreementTriggersApiOneOff(string $requestClass, ?string $expectAutoCapture): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/oneoff',
                    expectRequestMethod:'POST',
                    expectRequestBodyFieldsEqual: ['request_token' => true, 'token_agreement' => TokenAgreement::UNSCHEDULED, 'token_consent_agreed' => true],
                    responseStatusCode: 200,
                    responseContents:'{"payment_link": "https://example.com", "cc_details": {"token": "abc"}}',
                )
            ]
        );

        // Create gateway
        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.extension.request_token_for_capture' => new SetRequestTokenAgreementExtension(TokenAgreement::UNSCHEDULED, true),
        ]);

        // Create payment for Capture
        $payment = new Payment();
        $payment->setNumber('123');
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getAfterUrl')->willReturn('https://example.com');
        /** @var Generic $request */
        $request = new $requestClass($token);
        $request->setModel($payment);

        $exception = null;
        try {
            $gateway->execute($request);
        } catch (HttpRedirect $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertEquals('https://example.com', $exception->getUrl());
        $this->assertEquals(302, $exception->getStatusCode());

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals(PaymentType::ONE_OFF, $model['_type']);
        $this->assertEquals(['token' => 'abc'], $model['cc_details']);
        $this->assertEquals($expectAutoCapture, $model['_auto_capture_with_notify']);
    }

    public function testNewMitPaymentCaptureTriggersApiMitAndChargeAndCapture(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/mit',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled", "payment_reference": "123"}'
                ),
                new R(
                    expectRequestPath:'/api/v4/payments/charge',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                ),
                new R(
                    expectRequestPath:'/api/v4/payments/capture',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                )
            ]
        );

        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.extension.token_details_for_authorize' => new PrepareForMitPaymentExtension('abc', TokenAgreement::UNSCHEDULED),
        ]);

        // Create payment
        $payment = new Payment();
        $payment->setTotalAmount(123);
        $payment->setNumber('456');

        // Capture
        $request = new Capture($payment);
        $gateway->execute($request);
    }

    public function testNewMitPaymentAuthorizeTriggersApiMitAndCharge(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/mit',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled", "payment_reference": "123"}'
                ),
                new R(
                    expectRequestPath:'/api/v4/payments/charge',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                )
            ]
        );

        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.extension.token_details_for_authorize' => new PrepareForMitPaymentExtension('abc', TokenAgreement::UNSCHEDULED),
        ]);

        // Create payment
        $payment = new Payment();
        $payment->setTotalAmount(123);
        $payment->setNumber('456');

        // Authorize
        $request = new Authorize($payment);
        $gateway->execute($request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertArrayNotHasKey('foo', $model);
        $this->assertEquals(PaymentType::MIT, $model['_type']);
        $this->assertIsArray($model['token_details']);
        $this->assertEquals('abc', $model['token_details']['token']);
    }

    public function testAuthorizedMitPaymentCaptureTriggersApiCapture(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/capture',
                    expectRequestMethod:'POST',
                    expectRequestBodyFieldsEqual:['amount' => 1.00],
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                )
            ]
        );

        $gateway = $this->createTestGateway($clientMock, $messageFactory);

        // Create MIT authorized payment
        $payment = new Payment();
        $payment->setNumber('123');
        $payment->setTotalAmount(100); // This should overwrite the amount in details - the case with partial capture
        $payment->setDetails(
            [
                '_type' => PaymentType::MIT,
                'payment_reference' => 'abc',
                'payment_state' => PaymentState::SETTLED,
                'token_agreement' => TokenAgreement::UNSCHEDULED,
                'amount' => 1.23,
                'cc_details' => [
                    'token' => 'abc',
                ],
            ]
        );

        // Capture payment
        $request = new Capture($payment);
        $gateway->execute($request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals(PaymentType::MIT, $model['_type']);
    }

    #[DataProvider('newPaymentRequestExpectations')]

    public function testNewCitPaymentAuthorizeOrCaptureTriggersApiCitAndCharge(string $requestClass, ?string $expectAutoCapture): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/cit',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled", "payment_reference": "123"}'
                ),
                new R(
                    expectRequestPath:'/api/v4/payments/charge',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled", "payment_link": "https://igw-demo.every-pay.com/foo/bar"}'
                )
            ]
        );

        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.extension.token_details_for_authorize' => new PrepareForCitPaymentExtension('abc', TokenAgreement::UNSCHEDULED),
        ]);

        // Create payment
        $payment = new Payment();
        $payment->setTotalAmount(123);
        $payment->setNumber('456');

        // Token is needed in order to generate the URL user should be redirected
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())->method('getAfterUrl')->willReturn('https://example.com');

        /** @var Generic $request */
        $request = new $requestClass($token);
        $request->setModel($payment);

        $exception = null;
        try {
            $gateway->execute($request);
        } catch (HttpRedirect $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertEquals('https://igw-demo.every-pay.com/foo/bar', $exception->getUrl());
        $this->assertEquals(302, $exception->getStatusCode());

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals(PaymentType::CIT, $model['_type']);
        $this->assertEquals('https://example.com', $model['customer_url']);
        $this->assertEquals($expectAutoCapture, $model['_auto_capture_with_notify']);
    }

    public function testAuthorizedCitPaymentCaptureTriggersApiCapture(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/capture',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                )
            ]
        );

        $gateway = $this->createTestGateway($clientMock, $messageFactory);

        $payment = new Payment();
        $payment->setDetails(
            [
                '_type' => PaymentType::CIT,
                'payment_state' => PaymentState::SETTLED,
                'payment_reference' => 'abc',
            ]
        );
        $request = new Capture($payment);
        $gateway->execute($request);
    }

    public function testAutoCaptureWithNotifyTriggersApiCapture(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/abc',
                    expectRequestMethod:'GET',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled", "payment_reference": "abc"}'
                ),
                new R(
                    expectRequestPath:'/api/v4/payments/capture',
                    expectRequestMethod:'POST',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "settled"}'
                )
            ]
        );

        // Create gateway
        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.action.get_http_request' => new TestGetHttpRequestAction(query:[
                'payment_reference' => 'abc'
            ]),
        ]);

        $payment = new Payment();
        $payment->setDetails(
            [
                '_auto_capture_with_notify' => NotifyAction::AUTO_CAPTURE_QUEUED,
                'payment_state' => PaymentState::SETTLED,
                'amount' => 1.23,
            ]
        );
        $request = new Notify($payment);
        $gateway->execute($request);
    }

    public function testNotifyWithTokenAndIdentity(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/abc',
                    expectRequestMethod:'GET',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "failed", "payment_reference": "abc"}'
                )
            ]
        );

        // Create gateway
        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.action.get_http_request' => new TestGetHttpRequestAction(query:[
                'payment_reference' => 'abc',
                'order_reference' => 'def'
            ]),
            'payum.extension.storage' => new TestStorageExtension(),
        ]);

        // Notify
        $token = $this->createMock(TokenInterface::class);
        $identity = new Identity(1, Payment::class);
        $token->expects($this->atLeastOnce())->method('getDetails')->willReturn($identity);

        $request = new Notify($token);
        $gateway->execute($request);
        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals('failed', $model['payment_state']);
    }

    public function testNotifyWithTokenAndNoIdentityResolves(): void
    {
        // Mock and set expectations on network requests
        $messageFactory = $this->createHttpMessageFactory();
        $clientMock = $this->createClientMock(
            [
                new R(
                    expectRequestPath:'/api/v4/payments/abc',
                    expectRequestMethod:'GET',
                    responseStatusCode:200,
                    responseContents:'{"payment_state": "failed", "payment_reference": "abc"}'
                )
            ]
        );

        // Mock storage for identity resolving
        $storageMock = $this->createMock(StorageInterface::class);
        $payment = new TestPayment();
        $payment->setId(1);
        $payment->setDetails(['payment_reference' => 'abc']);
        $storageMock->expects($this->atLeastOnce())->method('findBy')->willReturn([$payment]);
        $storageMock->expects($this->atLeastOnce())->method('identify')->willReturn(new Identity($payment->getId(), $payment::class));

        // Create gateway
        $gateway = $this->createTestGateway($clientMock, $messageFactory, [
            'payum.action.get_http_request' => new TestGetHttpRequestAction(query:[
                'payment_reference' => 'abc',
                'order_reference' => 'def'
            ]),
            // Identity resolver extension must come before storage extension
            'payum.extension.notify_callback' => new CallbackNotificationIdentityResolverExtension($storageMock),
            // The storage extension is expected to intercept request with IdentityInterface model and load the payment
            'payum.extension.storage' => new TestStorageExtension(),
        ]);

        // Mock that token details is null (no identity)
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getDetails')->willReturn(null);

        $request = new Notify($token);
        $gateway->execute($request);
        $model = ArrayObject::ensureArrayObject($request->getModel());
        $this->assertEquals('failed', $model['payment_state']);
    }

    public function testNotifyWithTokenAndNoIdentityThrows(): void
    {
        // Create gateway
        $gateway = $this->createTestGateway();

        // Mock that token details is null (no identity)
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getDetails')->willReturn(null);

        $request = new Notify($token);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Caught Notify request with model set to null. This probably means you haven\'t registered identity resolver extension. See ' . CallbackNotificationIdentityResolverExtension::class);
        $gateway->execute($request);
    }

    public static function newPaymentRequestExpectations(): Generator
    {
        yield Authorize::class => [Authorize::class, null];
        yield Capture::class =>  [Capture::class, NotifyAction::AUTO_CAPTURE_QUEUED];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createTestGateway(?HttpClientInterface $httpClient = null, ?MessageFactory $messageFactory = null, ?array $config = []): GatewayInterface
    {
        $factory = new EveryPayGatewayFactory();
        $config = $config + [
            'payum.http_client' => $httpClient,
            'httplug.message_factory' => $messageFactory,
            'username' => 'test_username',
            'secret' => 'test_secret',
            'account_name' => 'test_account_name',
        ];
        return $factory->create($config);
    }
}
class TestStorageExtension implements ExtensionInterface
{
    public function onPreExecute(Context $context): void
    {
        $request = $context->getRequest();

        if (false == $request instanceof ModelAggregateInterface) {
            return;
        }

        if (false == $request instanceof ModelAwareInterface) {
            return;
        }

        if (!$request->getModel() instanceof IdentityInterface) {
            return;
        }

        $payment = new Payment();
        $payment->setNumber('def');
        $request->setModel($payment);
    }

    public function onExecute(Context $context): void
    {
    }

    public function onPostExecute(Context $context): void
    {
    }

}
class TestGetHttpRequestAction implements ActionInterface
{
    /**
     * @param null|array<string,mixed> $query
     */
    public function __construct(private ?array $query = null)
    {

    }
    /**
     * {@inheritDoc}
     *
     * @param GetHttpRequest $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        if ($this->query !== null) {
            $request->query = $this->query;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return $request instanceof GetHttpRequest;
    }
}
class TestPayment extends Payment
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
