<?php
namespace gateway\models;

use extpoint\yii2\base\Model;
use extpoint\yii2\exceptions\UnexpectedCaseException;
use gateway\enums\OrderState;
use gateway\enums\RecurringPeriodName;
use gateway\enums\TransactionKind;
use gateway\exceptions\GatewayException;
use gateway\GatewayModule;

use gateway\gateways\Base;
use yii\db\ActiveQueryInterface;
use yii\helpers\ArrayHelper;

/**
 * Db attributes
 * @property string|int $id
 * @property string $state
 * @property float $initialAmount
 * @property float $recurringAmount
 * @property string|null $recurringPeriodName
 * @property int $recurringPeriodScale
 * @property string|null $gatewayName
 * @property string|null $gatewayParamsJson
 *
 * Dynamic in library, but could be by database in app
 * @property string $publicId Id to show to user
 * @property string $title Short description
 * @property string $description Complete description
 * @property int $trialDays
 * @property float $gatewayInitialAmount
 * @property float $gatewayRecurringAmount
 * @property string|null $gatewayPaymentMethod
 *
 * Dynamic attributes and properties
 * @property string[] $gatewayParams
 * @property OrderItem[]|null $items
 * @property Transaction[] $transactions
 */
abstract class Order extends Model
{
    public function rules()
    {
        return [
            // Defaults
            ['state', 'default', 'value' => OrderState::READY],
            ['initialAmount', 'default', 'value' => 0],
            ['recurringAmount', 'default', 'value' => 0],

            // Types
            ['state', 'in', 'range' => OrderState::getKeys()],
            ['recurringPeriodName', 'in', 'range' => RecurringPeriodName::getKeys()],

            // Complex logic
            ['state', function() {
                if (!$this->isNewRecord) {
                    if (
                        $this->isAttributeChanged('state')
                        && $this->getOldAttribute('state') !== OrderState::READY
                        && !($this->getOldAttribute('state') === OrderState::SUBSCRIPTION_ACTIVE && $this->state === OrderState::COMPLETE)
                    ) {
                        $this->addError('state', \Yii::t('yii2-gateways', 'Order cannot be reverted from terminal states'));
                    }
                    elseif ($this->state === OrderState::COMPLETE && !$this->hasErrors('state')) {
                        $sum = 0.0;
                        foreach ($this->transactions as $transaction) {
                            if ($transaction->kind === TransactionKind::PAYMENT_RECEIVED) {
                                $sum += $transaction->sum;
                            }
                            else if($transaction->notes === 'Gateway Rejected: duplicate') {
                                $this->addError('state', \Yii::t('yii2-gateways', 'Protection. The transaction duplicates the previous transaction.'));
                                return;
                            }
                        }

                        if ((float)$this->gatewayInitialAmount > $sum + 0.0001) {
                            $this->addError('state', \Yii::t('yii2-gateways', 'Payment transactions mismatch the sum'));
                        }
                    }
                }
            }],
            ['initialAmount', function() {
                $items = $this->items;
                if ($items
                    && $this->initialAmount !== array_sum(ArrayHelper::getColumn($items, 'initialAmount'))
                ) {
                    $this->addError('initialAmount', \Yii::t('yii2-gateways', 'Initial amount or order is not equal the sum of initial amounts of its items'));
                }
            }],
            ['recurringAmount', function() {
                $items = $this->items;
                if ($items
                    && $this->recurringAmount !== array_sum(ArrayHelper::getColumn($items, 'recurringAmount'))
                ) {
                    $this->addError('recurringAmount', \Yii::t('yii2-gateways', 'Recurring amount or order is not equal the sum of recurring amounts of its items'));
                }
            }],
        ];
    }

    public function getItems()
    {
        // Note: order items are optional, implement in user code, when necessary
        return null /*
            null = not implemented.
            [] = implemented, but empty
        */;
    }

    /**
     * @return ActiveQueryInterface
     */
    abstract public function getTransactions();

    /**
     * @return Base
     */
    public function getGateway()
    {
        if (!$this->gatewayName) {
            throw new UnexpectedCaseException();
        }
        return GatewayModule::getInstance()->getGateway($this->gatewayName);
    }

    public function getGatewayParams()
    {
        return $this->gatewayParamsJson ? json_decode($this->gatewayParamsJson, true) : [];
    }

    public function setGatewayParams($data)
    {
        $this->gatewayParamsJson = json_encode($data);
    }

    public function getPublicId()
    {
        return $this->id;
    }

    /**
     * @param $id
     * @return Order|null
     */
    public static function findByPublicId($id)
    {
        return static::findOne((string)$id);
    }

    public function getTitle()
    {
        throw new GatewayException('Implement this in user code');
    }

    public function getDescription()
    {
        throw new GatewayException('Implement this in user code');
    }

    public function getTrialDays()
    {
        return 0;
    }

    public function getGatewayInitialAmount()
    {
        return $this->initialAmount;
    }

    public function getGatewayRecurringAmount()
    {
        return $this->recurringAmount;
    }

    /**
     * @return string|null
     */
    public function getGatewayPaymentMethod()
    {
        return null;
    }


    /////// Scenario methods ///////////////////////////

    /**
     * @param string $externalTransactionId
     * @param string|null $transactionNotes
     * @param string|null $logId
     * @param array $gatewayExtra
     */
    public function processPaymentReceived($externalTransactionId, $logId = null, $transactionNotes = null, $gatewayExtra = [])
    {
        /** @var Transaction $transactionClass */
        $transactionClass = GatewayModule::getInstance()->transactionClassName;
        // Skip already handled events
        $transaction = $transactionClass::findOne(['externalEventId' => $externalTransactionId]);
        if ($transaction) {
            return;
        }

        // Detect payment type
        $isInitial = !$this->getTransactions()
            ->where(['kind' => TransactionKind::PAYMENT_RECEIVED])
            ->exists();

        // Log
        /** @var Transaction $transaction */
        $transaction = new $transactionClass();
        $transaction->kind = TransactionKind::PAYMENT_RECEIVED;
        $transaction->logId = $logId;
        $transaction->orderId = $this->id;
        $transaction->notes = $transactionNotes;
        $transaction->sum = $isInitial ? $this->initialAmount : $this->recurringAmount;
        $transaction->externalEventId = $externalTransactionId;
        if ($gatewayExtra) {
            $transaction->gatewayExtra = $gatewayExtra;
        }
        $transaction->saveOrPanic();

        // Complete only on single payment
        if (!$this->recurringAmount) {
            $this->state = OrderState::COMPLETE;
            $this->saveOrPanic();
        }
    }
}
