<?php

namespace gateway\gateways;

use gateway\exceptions\FeatureNotSupportedByGatewayException;
use gateway\exceptions\InvalidDatabaseStateException;
use gateway\GatewayModule;
use gateway\models\Order;
use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\helpers\Url;
use yii\log\Logger;
use yii\web\Response;

/**
 * Class Base
 * @package gateway\gateways
 */
abstract class Base extends Object
{
    /**
     * Флаг, отображающий включена ли платёжный шлюз.
     * @var boolean
     */
    public $enable = true;

    /**
     * Способ оплаты. Поле актуально только для платёжных интеграторов, где есть выбор способа оплаты.
     * @var string|null
     */
    public $paymentMethod = null;

    /**
     * Флаг, отображающий включен ли платёжный шлюз для реальных транзакций.
     * По-умолчанию включен режим разработчика.
     * @var boolean
     */
    public $testMode = true;

    /**
     * Имя платёжного шлюза в GatewayModule->$gateways
     * @var string
     */
    public $name;

    /**
     *
     * @var GatewayModule
     */
    public $module;

    /**
     * @param Order $order
     * @return Response|string
	 * @throws FeatureNotSupportedByGatewayException
     */
    public function start($order)
	{
		// Check sanity
		if ($order->trialDays > 0 && !$this->supportsTrial()) {
			throw new FeatureNotSupportedByGatewayException('Trials are not supported by ' . static::className());
		}
		if ($order->recurringAmount != 0 && !$this->supportsRecurring()) {
			throw new FeatureNotSupportedByGatewayException('Recurring payments are not supported by ' . static::className());
		}

		// Gateways must update the model explicitly in one place
		// Don't: // $order->gatewayName = $this->name;

		return $this->internalStart($order);
	}

	/**
	 * @param Order $order
	 * @return Response|string
	 */
	abstract protected function internalStart($order);

    /**
	 * @param int $logId
	 * @return Response|string
     */
    abstract public function callback($logId);


    public function supportsRecurring()
	{
		return false;
	}

    public function supportsTrial()
	{
		return false;
	}

	/**
	 * @param Order|null $order Anonymous gateways (e.g. bitaps.com) supports custom callback URLs
	 * @return string
	 * @throws InvalidConfigException
	 */
    public function getCallbackUrl($order = null)
	{
		$url = $this->module->callbackUrl;
		if (!is_array($this->module->callbackUrl)) {
			throw new InvalidConfigException();
		}
		$url['gatewayName'] = $this->name;
		return Url::to($url);
	}

    /**
     * @param $message
     * @param integer $level
     * @param null $transactionId
     * @param array $stateData
     */
    protected function log($message, $level = Logger::LEVEL_INFO, $transactionId = null, $stateData = array())
    {
        $this->module->log($message, $level, $transactionId, $stateData);
    }

    protected function httpSend($url, $params = [], $headers = [])
    {
        return $this->module->httpSend($url, $params, $headers);
    }

	/**
	 * @param string $orderId
	 * @return Order
	 * @throws InvalidConfigException
	 * @throws InvalidDatabaseStateException
	 */
    protected function getOrderById($orderId)
	{
        $orderClassName = $this->module->orderClassName;
		$order = $orderClassName::findOne((string)$orderId);

        if (!$order) {
			throw new InvalidDatabaseStateException('Order not found');
		}

        return $order;
    }
}
