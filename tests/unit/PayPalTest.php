<?php

namespace gateway\tests\unit;

use gateway\enums\RecurringPeriodName;
use gateway\GatewayModule;
use gateway\gateways\Base;
use gateway\gateways\PayPal;
use gateway\models\Order;
use gateway\tests\unit\helpers\AppTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class PayPalTest extends AppTestCase
{
    const POSSIBLE_ROUNDING_MISTAKE = GatewayModule::MONEY_EPSILON / 2;

    /** @var GatewayModule|MockObject */
    private $module;

    /** @var MockObject */
    private $i18nMock;

    /** @var MockObject */
    private $appMock;

    /** @var MockObject */
    private $urlMock;

    public function testOneTimePaymentStart()
    {
        // Arrange
        $gateway = new PayPal([
            'enable' => true,
            'testMode' => false,
            'name' => 'paypal',
            'module' => $this->module,
            'merchantEmail' => 'business@example.com',
        ]);

        /** @var Order $order */
        $order = $this->createModelMock('\gateway\models\Order', [
            'gatewayInitialAmount' => 100 + self::POSSIBLE_ROUNDING_MISTAKE,
            'title' => 'Sample Product',
            'slug' => 'order-slug',
        ]);

        // Act
        $response = $gateway->start($order);

        // Assert
        $this->assertEquals(
            Base::redirectPost('https://www.paypal.com/cgi-bin/webscr', [
                'cmd' => '_xclick',
                'amount' => '100.00',
                'business' => 'business@example.com',
                'currency_code' => 'USD',
                'item_name' => 'Sample Product',
                'no_shipping' => 1,
                'item_number' => 'order-slug',
                'return' => 'https://example.com/success/',
                'cancel_return' => 'https://example.com/failure/',
                'notify_url' => 'https://example.com/callback/',
                'charset' => 'utf-8',
                'lc' => 'en-US',
            ]),
            $response
        );
    }

    public function testOneTimePaymentCallback()
    {
        // Arrange
        $gateway = new PayPalPartiallyMocked([
            'enable' => true,
            'testMode' => false,
            'name' => 'paypal',
            'module' => $this->module,
            'merchantEmail' => 'business@example.com',
            'testCallbackPost' => $this->readJsonFixture('one-time-payment-callback.json'),
        ]);

        $order = $this->createModelMock('\gateway\models\Order', [
            'gatewayInitialAmount' => 100 + self::POSSIBLE_ROUNDING_MISTAKE,
            'title' => 'Sample Product',
            'slug' => 'order-slug',
        ]);
        $order->method('getGateway')->willReturn($gateway);
        $this->trackCalls($order, 'processPaymentReceived', $processPaymentReceivedCalls);
        /** @var Order $order */
        $orderClass = $this->createStaticClassMock([
            'findByPublicId' => $order,
        ]);
        $this->module->orderClassName = $orderClass;

        $logId = 6;

        // Act
        $response = $gateway->callback($logId);

        // Assert
        $this->assertEquals('', $response);
        $this->assertEquals([
            [
                '0HY72019N63633221', // $externalTransactionId <- POST txn_id
                $logId,
                null, // $externalSubscriptionId <- POST subscr_id ?? null
                null, // $transactionNotes, default
                [], // $gatewayExtra, default
            ],
        ], $processPaymentReceivedCalls);
    }

    protected function setUp(): void
    {
        $this->module = $this->createSafeMock('gateway\GatewayModule');
        $this->module->method('getEffectiveSuccessUrl')->willReturn('https://example.com/success/');
        $this->module->method('getEffectiveFailureUrl')->willReturn('https://example.com/failure/');
        $this->module->callbackUrl = 'https://example.com/callback/';

        $this->i18nMock = $this->createSafeMock('\yii\i18n\I18N');
        $this->i18nMock->method('translate')->willReturnCallback(function ($category, $message) { return $message; });

        $this->appMock = $this->createSafeMock('\yii\web\Application');
        $this->appMock->method('getI18n')->willReturn($this->i18nMock);
        $this->appMock->method('getRequest')->willReturn(null);
        \Yii::$app = $this->appMock;

        $this->urlMock = $this->mockStaticClass('\yii\helpers\Url');
        $this->urlMock->method('to')->willReturnMap([
            // Html form passes any URLs trough Url::to()
            ['https://www.paypal.com/cgi-bin/webscr', 'https://www.paypal.com/cgi-bin/webscr'],
        ]);
    }
}

class PayPalPartiallyMocked extends PayPal
{
    public $testCallbackPost;

    protected function verifyIPN()
    {
        return $this->testCallbackPost;
    }
}
