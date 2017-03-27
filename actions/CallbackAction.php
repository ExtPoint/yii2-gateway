<?php

namespace gateway\actions;

use gateway\GatewayModule;
use gateway\models\CallbackLogEntry;
use yii\base\Action;


class CallbackAction extends Action
{
    public function actionCallback($gatewayName)
    {
    	// Start logging
		/** @var CallbackLogEntry $logEntry */
		$logEntry = \Yii::createObject(GatewayModule::getInstance()->callbackLogEntryClassName);
		$logEntry->setRequest([
			'get' => $_GET,
			'post' => $_POST,
			'cookie' => $_COOKIE,
			'server' => $_SERVER,
		]);
		GatewayModule::saveOrPanic($logEntry);

		// Execute
		$failed = false;
		try {
			$result = GatewayModule::getInstance()->getGateway($gatewayName)->callback($logEntry->id);
		}
		catch (\Throwable $e) {
			$failed = true;
			$result = $e;
		}

		// Log result
		$logEntry->setResponse($result);
		GatewayModule::saveOrPanic($logEntry);

		// Escalate result
        if ($failed) {
			throw new $result;
		}
		return $result;
    }
}
