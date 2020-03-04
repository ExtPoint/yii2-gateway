<?php

namespace gateway;

use gateway\exceptions\NotFoundGatewayException;
use gateway\gateways\Base;
use gateway\models\CallbackLogEntry;
use gateway\models\Order;
use extpoint\yii2\base\Module;
use gateway\models\Transaction;
use yii\helpers\Url;
use yii\log\Logger;

class GatewayModule extends Module
{
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
     * @var Transaction
     */
    public $transactionClassName;

    /**
     * @var CallbackLogEntry
     */
    public $callbackLogEntryClassName;

    /**
     * @return \gateway\GatewayModule
     */
    public static function getInstance()
    {
        return \Yii::$app->getModule('gateway');
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

    public function hasGateway($name)
    {
        return isset($this->gateways[$name]);
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
            $this->gateways[$name] = \Yii::createObject(
                ['name' => $name, 'module' => $this]
                + $this->gateways[$name]
            );
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
}
