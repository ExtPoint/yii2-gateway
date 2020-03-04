<?php

namespace gateway\actions;

use yii\base\Action;
use yii\web\Response;

class StubGatewayAction extends Action
{
    public function run()
    {
        $post = file_get_contents('php://input');

        \Yii::$app->response->format = Response::FORMAT_RAW; // Not JSON, because formatting
        \Yii::$app->response->headers->add('Content-Type', 'application/json');
        return json_encode([
            'method' => \Yii::$app->request->method,
            'url' => \Yii::$app->request->url,
            'headers' => array_map(
                function($value) { return count($value) == 1 ? $value[0] : $value; },
                \Yii::$app->request->headers->toArray()
            ),
            'post' => $post,
        ], JSON_PRETTY_PRINT);
    }
}
