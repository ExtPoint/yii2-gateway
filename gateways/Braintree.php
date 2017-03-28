<?php

namespace gateway\gateways;

use Braintree\Configuration;
use Braintree\Gateway;
use gateway\enums\TransactionKind;
use Yii;
use gateway\exceptions\GatewayException;
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
        //$this->getConnection()->subscription()->find();
    }

    /**
     * @param int $logId
     * @return Response|mixed
     */
    public function callback($logId)
    {
    }

}