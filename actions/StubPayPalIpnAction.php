<?php

namespace gateway\actions;

use yii\base\Action;
use yii\web\Response;

class StubPayPalIpnAction extends Action
{
    public function run($isValid = '1')
    {
        \Yii::$app->response->format = Response::FORMAT_RAW;
        return $isValid ? 'VERIFIED' : 'INVALID';
    }
}
