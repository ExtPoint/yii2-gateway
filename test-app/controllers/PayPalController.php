<?php
namespace app\controllers;

use app\models\TestOrder;
use gateway\GatewayModule;
use yii\web\Controller;

class PayPalController extends Controller
{
    public function actionIndex()
    {
        return $this->renderContent('
            <base target="run">
            <ul class="my-2">
                <li><a href="/pay-pal/one-time-payment-start">One Time Payment $100</a></li>
                <li><a href="/pay-pal/subscription-start">Subscribe $100@2M, $50 initial</a></li>
            </ul>
        ');
    }

    public function actionOneTimePaymentStart()
    {
        $gateway = GatewayModule::getInstance()->getGateway('paypal');

        $order = new TestOrder([
            'initialAmount' => 100,
            'slug' => 'order-slug',
        ]);

        return $gateway->start($order);
    }
}
