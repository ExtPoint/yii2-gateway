<?php

namespace gateway\actions;

use gateway\GatewayModule;
use gateway\models\CallbackLogEntry;
use yii\base\Action;


class CallbackAction extends Action
{
    public function run()
    {
        $gatewayName = \Yii::$app->request->get('gatewayName');
        $module = GatewayModule::getInstance();

        // Start logging
        /** @var CallbackLogEntry $logEntry */
        $logEntry = \Yii::createObject($module->callbackLogEntryClassName);
        $logEntry->setRequest([
            'get' => $_GET,
            'post' => $_POST,
            'cookie' => $_COOKIE,
            'server' => $_SERVER,
        ]);
        $logEntry->saveOrPanic();
        $start = microtime(true);

        // Execute
        $failed = false;
        try {
            $result = $module->getGateway($gatewayName)->callback($logEntry->id);
        }
        catch (\Throwable $e) {
            $failed = true;
            $result = $e;
        }

        // Log result
        $logEntry->duration = microtime(true) - $start;
        $logEntry->setResponse($result);
        $logEntry->saveOrPanic();

        // Escalate result
        if (!$failed) {
            return $result;
        }
        if (
            $module->hasGateway($gatewayName) &&
            ($result2 = $module->getGateway($gatewayName)->getResponseFromException($result))
        ) {
            return $result2;
        }

        // Regular exception flow
        throw $result;
    }
}
