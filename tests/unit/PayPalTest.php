<?php

namespace gateway\tests\unit;

use gateway\GatewayModule;
use gateway\gateways\Base;
use gateway\gateways\PayPal;
use gateway\models\Order;
use gateway\tests\unit\helpers\AppTestCase;

class PayPalTest extends AppTestCase {

    public function testPay() {
        // Arrange
        $module = $this->createSafeMock('gateway\GatewayModule');
        $module->method('getEffectiveSuccessUrl')->willReturn('https://example.com/success/');
        $module->method('getEffectiveFailureUrl')->willReturn('https://example.com/failure/');
        /** @var GatewayModule $module */
        $module->callbackUrl = 'https://example.com/callback/';

        $i18nMock = $this->createSafeMock('\yii\i18n\I18N');
        $i18nMock->method('translate')->willReturnCallback(function ($category, $message) { return $message; });

        $appMock = $this->createSafeMock('\yii\web\Application');
        $appMock->method('getI18n')->willReturn($i18nMock);
        $appMock->method('getRequest')->willReturn(null);
        \Yii::$app = $appMock;

        $urlMock = $this->mockStaticClass('\yii\helpers\Url');
        $urlMock->method('to')->willReturnMap([
            // Html form
            ['https://www.paypal.com/cgi-bin/webscr', 'https://www.paypal.com/cgi-bin/webscr'],
        ]);

        $gateway = new PayPal([
            'enable' => true,
            'testMode' => false,
            'name' => 'paypal',
            'module' => $module,
        ]);

        /** @var Order $order */
        $order = $this->createModelMock('\gateway\models\Order', [
            'gatewayInitialAmount' => 100,
            'title' => 'Sample Product',
            'slug' => 'order-slug',
        ]);

        // Act
        $response = $gateway->start($order);

        // Assert
        $this->assertEquals(
            Base::redirectPost('https://www.paypal.com/cgi-bin/webscr', [
                'cmd' => '_xclick',
                'amount' => 100,
                'business' => '',
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
}
