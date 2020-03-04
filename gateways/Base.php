<?php

namespace gateway\gateways;

use extpoint\yii2\exceptions\NotImplementedException;
use gateway\exceptions\FeatureNotSupportedByGatewayException;
use gateway\exceptions\InvalidDatabaseStateException;
use gateway\GatewayModule;
use gateway\models\Order;
use gateway\models\Transaction;
use yii\base\InvalidConfigException;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\log\Logger;
use yii\web\Response;

/**
 * Class Base
 * @package gateway\gateways
 */
abstract class Base extends BaseObject
{
    /**
     * Флаг, отображающий включена ли платёжный шлюз.
     * @var boolean
     */
    public $enable = true;

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
     * @param array $noSaveParams Credit card data, nonce, gateway-passthrough, etc
     * @return Response|string
     * @throws FeatureNotSupportedByGatewayException
     */
    public function start($order, $noSaveParams = [])
    {
        // Check sanity
        if ($order->trialDays > 0 && !$this->supportsTrial()) {
            throw new FeatureNotSupportedByGatewayException('Trials are not supported by ' . static::class);
        }
        if ($order->gatewayRecurringAmount != 0 && !$this->supportsRecurring()) {
            throw new FeatureNotSupportedByGatewayException('Recurring payments are not supported by ' . static::class);
        }

        // Gateways must update the model explicitly in one place
        // Don't: // $order->gatewayName = $this->name;

        return $this->internalStart($order, $noSaveParams);
    }

    /**
     * @param Order $order
     * @param array $noSaveParams
     * @return Response|string
     */
    protected function internalStart($order, $noSaveParams = [])
    {
        throw new NotImplementedException();
    }

    /**
     * @param int $logId
     * @return Response|string|mixed
     */
    public function callback($logId) {
        throw new NotImplementedException();
    }

    public function supportsRecurring()
    {
        return false;
    }

    public function supportsTrial()
    {
        return false;
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getSiteUrl($order)
    {
        return $this->module->getEffectiveSiteUrl($order, $this);
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getSuccessUrl($order)
    {
        return $this->module->getEffectiveSuccessUrl($order, $this);
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getFailureUrl($order)
    {
        return $this->module->getEffectiveFailureUrl($order, $this);
    }

    /**
     * @param Order|null $order Anonymous gateways (e.g. bitaps.com) support custom callback URLs
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
        return Url::to($url, true);
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
     * Redirects the browser to the specified URL.
     * This method is a shortcut to [[Response::redirect()]].
     *
     * You can use it in an action by returning the [[Response]] directly:
     *
     * ```php
     * // stop executing this action and redirect to login page
     * return $this->redirect(['login']);
     * ```
     *
     * @param string|array $url the URL to be redirected to. This can be in one of the following formats:
     *
     * - a string representing a URL (e.g. "http://example.com")
     * - a string representing a URL alias (e.g. "@example.com")
     * - an array in the format of `[$route, ...name-value pairs...]` (e.g. `['site/index', 'ref' => 1]`)
     *   [[Url::to()]] will be used to convert the array into a URL.
     *
     * Any relative URL will be converted into an absolute one by prepending it with the host info
     * of the current request.
     *
     * @param int $statusCode the HTTP status code. Defaults to 302.
     * See <http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html>
     * for details about HTTP status code
     * @return Response the current response object
     */
    public function redirect($url, $statusCode = 302)
    {
        return \Yii::$app->getResponse()->redirect(Url::to($url), $statusCode);
    }

    /**
     * @param string $url
     * @param string[] $postFields
     * @return string|Response
     */
    public function redirectPost($url, $postFields)
    {
        $formId = 'payment-redirect';

        $result = Html::tag(
            'div',
            htmlspecialchars(\Yii::t('yii2-gateways', 'Opening payment form, please wait')),
            ['class' => 'alert alert-info']
        );

        $result .= Html::beginForm($url, 'post', ['id' => $formId]);

        foreach ($postFields as $field => $value) {
            $result .= Html::hiddenInput($field, $value);
        }

        $result .= Html::endForm();
        $result .= "<script>\ndocument.getElementById('$formId').submit();\n</script>";

        return $result;
    }

    /**
     * @param \Throwable $e
     * @return Response|string|mixed|null null = unsupported
     */
    public function getResponseFromException(\Throwable $e)
    {
        return null;
    }

    /**
     * @param string $orderId
     * @return Order
     * @throws InvalidConfigException
     * @throws InvalidDatabaseStateException
     */
    public function requireOrderByPublicId($orderId)
    {
        $orderClassName = $this->module->orderClassName;
        $order = $orderClassName::findByPublicId($orderId);

        if (!$order) {
            throw new InvalidDatabaseStateException('Order not found');
        }

        return $order;
    }

    /**
     * @param string $kind
     * @param string $orderId
     * @param string|null $logId Never pass null inside callback(). Null is for instant responses.
     * @return Transaction
     */
    public function prepareTransaction($kind, $orderId, $logId)
    {
        $transactionClassName = $this->module->transactionClassName;

        /** @var Transaction $transaction */
        $transaction = new $transactionClassName();
        $transaction->kind = $kind;
        $transaction->orderId = $orderId;
        $transaction->logId = $logId;

        return $transaction;
    }

    /**
     * Shortcut
     * @param string $kind
     * @param string $orderId
     * @param string|null $logId Never pass null inside callback(). Null is for instant responses.
     * @param string|null $notes
     * @param float|null $sum
     * @param string|null $externalEventId
     * @param string|null $externalSubscriptionId
     * @param string|null $externalInvoiceId
     * @param array $gatewayExtra
     */
    public function logTransaction($kind, $orderId, $logId, $notes = null, $sum = null, $externalEventId = null,
                                   $externalSubscriptionId = null, $externalInvoiceId = null, $gatewayExtra = [])
    {
        /** @var Transaction $transaction */
        $transaction = $this->prepareTransaction($kind, $orderId, $logId);

        $transaction->notes = $notes;
        $transaction->sum = $sum;
        $transaction->externalEventId = $externalEventId;
        $transaction->externalSubscriptionId = $externalSubscriptionId;
        $transaction->externalInvoiceId = $externalInvoiceId;
        if ($gatewayExtra) {
            $transaction->gatewayExtra = $gatewayExtra;
        }

        $transaction->saveOrPanic();
    }
}
