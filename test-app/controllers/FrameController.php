<?php
namespace app\controllers;

use app\models\TestOrder;
use gateway\GatewayModule;
use yii\web\Controller;

class FrameController extends Controller
{
    public function actionIndex()
    {
        return $this->renderContent('
            <div class="d-flex" style="height: 100vh">
                <div class="d-flex flex-column" style="width: 400px">
                    <iframe src="/frame/list-gateways" class="d-block b-0" style="height: 70px"></iframe>
                    <iframe name="scenarios" src="/pay-pal/" class="d-block" style="height: 70px"></iframe>
                    <iframe name="scenarios" src="/frame/logs" class="d-block flex-fill"></iframe>
                </div>
                <iframe name="run" src="about:blank" class="d-block flex-fill"></iframe>
            </div>
        ');
    }

    public function actionListGateways()
    {
        return $this->renderContent('
            <base target="scenarios">
            <ul class="my-2">
                <li><a href="/pay-pal/">PayPal</a></li>
                <li><a href="/stripe/">Stripe</a></li>
            </ul>
        ');
    }

    public function actionSuccess()
    {
        return $this->renderContent('Success page');
    }

    public function actionFailure()
    {
        return $this->renderContent('Failure page');
    }

    public function actionLogs()
    {
        return $this->renderContent('
            <div class="container-fluid mt-2">Logs will go here</div>
        ');
    }
}
