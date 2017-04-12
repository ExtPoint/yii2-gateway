<?php

namespace gateway\gateways;

use Braintree\Configuration;
use Braintree\Gateway;
use Braintree\WebhookNotification;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * @property Gateway $connection
 */
class Braintree extends Base
{
    public $environment;
    public $merchantId;
    public $publicKey;
    public $privateKey;

    private $_connection;

    public function supportsRecurring()
    {
        return true;
    }

    /**
     * @return Gateway
     * @throws InvalidConfigException
     */
    public function getConnection()
    {
        if ($this->_connection === null) {
            if (!$this->environment) {
                throw new InvalidConfigException('Environment is required');
            }
            if (!$this->merchantId) {
                throw new InvalidConfigException('Merchant ID is required');
            }
            if (!$this->publicKey) {
                throw new InvalidConfigException('Public Key is required');
            }
            if (!$this->privateKey) {
                throw new InvalidConfigException('Private Key is required');
            }

            $configuration = new Configuration();
            $configuration->setEnvironment($this->environment);
            $configuration->setMerchantId($this->merchantId);
            $configuration->setPublicKey($this->publicKey);
            $configuration->setPrivateKey($this->privateKey);

            $this->_connection = new Gateway($configuration);
        }

        return $this->_connection;
    }

    protected function internalStart($order, $noSaveParams = [])
    {
    }

    /**
     * @param int $logId
     * @return Response|mixed
     */
    public function callback($logId)
    {
        // Note: WebhookNotification class supports only global configuration
        Configuration::merchantId($this->merchantId);
        Configuration::environment($this->environment);
        Configuration::publicKey($this->publicKey);
        Configuration::privateKey($this->privateKey);

        $webHookNotification = WebhookNotification::parse(
            \Yii::$app->request->post('bt_signature'),
            \Yii::$app->request->post('bt_payload'));

        $subscriptionId = isset($webHookNotification->subscription)
            ? $webHookNotification->subscription->id
            : null;

        $transactionId = isset($webHookNotification->transaction)
            ? $webHookNotification->transaction->id
            : null;

        $sum = $transactionId
            ? $webHookNotification->transaction->amount
            : null;

        if(!$sum && $subscriptionId && count($webHookNotification->subscription->transactions) > 0){
            $sum = $webHookNotification->subscription->transactions[0]->amount;
        }

        $this->logTransaction($webHookNotification->kind, null, $logId, null, $sum, $transactionId, $subscriptionId);

        // TODO: return 'something';
    }

}