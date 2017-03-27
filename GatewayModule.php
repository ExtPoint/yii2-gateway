<?php

namespace gateway;

use gateway\components\IOrderInterface;
use gateway\components\IStateSaver;
use gateway\enums\OrderState;
use gateway\exceptions\GatewayException;
use gateway\exceptions\NotFoundGatewayException;
use gateway\gateways\Base;
use gateway\models\Order;
use gateway\models\Process;
use gateway\models\Request;
use yii\base\Module;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\log\Logger;
use yii\web\Application;
use yii\web\Controller;

class GatewayModule extends Module
{
    /**
     * @event ProcessEvent
     */
    const EVENT_START = 'start';

    /**
     * @event ProcessEvent
     */
    const EVENT_CALLBACK = 'callback';

    /**
     * @event ProcessEvent
     */
    const EVENT_END = 'end';

    /**
     * @var string
     */
    public $controllerNamespace = 'gateway\controllers';

    /**
     * @var array
     */
    public $gateways = [];

    /**
     * @var string
     */
    public $logFilePath = '@runtime/gateway.log';

    /**
     * @var string|array|callable|null
     */
    public $siteUrl = null;

    /**
     * @var string|array|callable
     */
    public $successUrl = ['/gateway/gateway/success'];

    /**
     * @var string|array|callable
     */
    public $failureUrl = ['/gateway/gateway/failure'];

    /**
     * @var array Note: processed by gateway, so format is unified: no string, no callback
     */
    public $callbackUrl = ['/gateway/gateway/callback'];

	/**
	 * @var Order
	 */
	public $orderClassName;

	/**
	 * @var Order
	 */
	public $transactionClassName;

	/**
	 * @var Order
	 */
	public $callbackLogEntryClassName;

    /**
     * @return \gateway\GatewayModule
     */
    public static function getInstance()
	{
        return \Yii::$app->getModule('gateway');
    }

    public function init()
	{
        parent::init();

        if (\Yii::$app instanceof Application) {
            $this->siteUrl = $this->siteUrl ? Url::to($this->siteUrl, true) : \Yii::$app->homeUrl;
            $this->successUrl = Url::to($this->successUrl, true);
            $this->failureUrl = Url::to($this->failureUrl, true);
        } else {
            $this->successUrl = is_array($this->successUrl) ? $this->successUrl[0] : $this->successUrl;
            $this->failureUrl = is_array($this->failureUrl) ? $this->failureUrl[0] : $this->failureUrl;
        }
    }

	/**
	 * @param string|callable|array|null $url
	 * @param Order $order
	 * @param Base $gateway
	 * @return string
	 */
    protected function expandUrl($url, $order, $gateway)
	{
		if ($url === null) {
			return Url::home(true);
		}
		if (is_callable($url)) {
			return call_user_func($url, $order, $gateway);
		}
		return Url::to($url, true);
	}

	/**
	 * @param Order $order
	 * @param Base $gateway
	 * @return string
	 */
    public function getEffectiveSiteUrl($order, $gateway)
	{
		return $this->expandUrl($this->siteUrl, $order, $gateway);
	}

	/**
	 * @param Order $order
	 * @param Base $gateway
	 * @return string
	 */
    public function getEffectiveSuccessUrl($order, $gateway)
	{
		return $this->expandUrl($this->successUrl, $order, $gateway);
	}

	/**
	 * @param Order $order
	 * @param Base $gateway
	 * @return string
	 */
    public function getEffectiveFailureUrl($order, $gateway)
	{
		return $this->expandUrl($this->failureUrl, $order, $gateway);
	}

    /**
     * @param string $name
     * @return Base
     * @throws NotFoundGatewayException
     * @throws \yii\base\InvalidConfigException
     */
    public function getGateway($name)
	{
		if (!isset($this->gateways[$name])) {
			throw new NotFoundGatewayException();
		}

		if (is_array($this->gateways[$name])) {
			$this->gateways[$name] = \Yii::createObject(['name' => $name] + $this->gateways[$name]);
		}

    	return $this->gateways[$name];
    }

    /**
     * @param string $message
     * @param integer $level
     * @param integer $transactionId
     * @param array $stateData
     * @throws \gateway\exceptions\GatewayException
     */
    public function log($message, $level = Logger::LEVEL_INFO, $transactionId = null, $stateData = [])
	{
        $message .= "\n" .
            "Transaction: " . $transactionId . "\n" .
            "State: " . print_r($stateData, true) . "\n\n" .
            "------------------------------------------\n\n";
        \Yii::getLogger()->log($message, $level, 'gateway');
    }

    /**
     * Отправляет POST запрос на указанный адрес
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return string
     */
    public function httpSend($url, $params = [], $headers = [])
	{
        $headers = array_merge([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $headers);

        $headersString = '';
        foreach ($headers as $key => $value) {
            $headersString .= trim($key) . ": " . trim($value) . "\n";
        }

        return file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => $headersString . "\n",
                'content' => is_array($params) ? http_build_query($params) : $params,
            ),
        )));
    }

	/**
	 * @param ActiveRecord $model
	 * @throws GatewayException
	 */
    public static function saveOrPanic($model)
	{
		if (!$model->save()) {
			throw new GatewayException('Unexpected ' . $model->className() . ' behavior');
		}
	}

}
