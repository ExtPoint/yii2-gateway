<?php

namespace gateway\actions;

use gateway\GatewayModule;
use gateway\models\CallbackLogEntry;
use yii\base\Action;


class CallbackAction extends Action
{
    public function actionCallback($gatewayName)
    {
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
		GatewayModule::saveOrPanic($logEntry);

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
		$logEntry->setResponse($result);
		GatewayModule::saveOrPanic($logEntry);

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
		throw new $result;
    }
}
