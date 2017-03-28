<?php

namespace gateway\controllers;

use gateway\actions\CallbackAction;
use yii\web\Controller;

class GatewayController extends Controller
{
    public function beforeAction($action)
    {
        // Enable m2m POST requests
        if ($action instanceof CallbackAction) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actions()
    {
        return [
            'callback' => CallbackAction::className(),
        ];
    }

    /* Example on how to place an order

    public function actionBuy()
    {
        $order = new Order();
        // Fill it somehow
        $order->saveOrPanic();

        $result = GatewayModule::getInstance()->getGateway('selectedGateway')->start($order);

        return is_string($result)
            ? $this->renderContent($result)
            : $result;
    }*/

    public function actionSuccess()
    {
        return $this->redirect(\Yii::$app->homeUrl);
    }

    public function actionFailure()
    {
        $error = \Yii::$app->request->get('error');
        if ($error) {
            return $this->renderContent(nl2br(htmlspecialchars($error)));
        }

        return $this->renderContent(\Yii::t('yii2-gateways', 'We are sorry but the payment is failed. Please try again or contact our support'));
    }
}
