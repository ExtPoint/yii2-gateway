<?php
namespace gateway\models;

use gateway\enums\TransactionKind;
use yii\db\ActiveRecord;

/**
 * @property string|int $id
 * @property string $kind
 * @property string|int|null $orderId
 * @property string|int|null $logId
 * @property string|null $externalEventId
 * @property string|null $externalSubscriptionId
 * @property string|null $externalInvoiceId
 * @property float|null $sum Payment to us > 0, refund or payoff < 0
 * @property string|null $notes
 * @property string|null $gatewayExtraJson
 *
 * @property Order|null $order
 * @property array $gatewayExtra
 */
abstract class Transaction extends ActiveRecord
{
	public function rules()
	{
		return [
			[['kind'], 'required'],
			['kind', 'range', 'in' => TransactionKind::getKeys()],
			[['sum'], 'number'],

			// Complex logic
			['kind', function() {
				if ($this->kind === TransactionKind::PAYMENT_RECEIVED) {
					if (!($this->sum > 0) && !$this->hasErrors('sum')) {
						$this->addError('sum', \Yii::t('yii2-gateways', 'Sum is required and must be positive for real payments'));
					}
				}
				if ($this->kind === TransactionKind::PAYMENT_REFUNDED || $this->kind === TransactionKind::PAYOFF_MADE) {
					if (!($this->sum < 0) && !$this->hasErrors('sum')) {
						$this->addError('sum', \Yii::t('yii2-gateways', 'Sum is required and must be negative for real refunds and payoffs'));
					}
				}
			}],
		];
	}

	public function afterSave($insert, $changedAttributes)
	{
		parent::afterSave($insert, $changedAttributes);
	}

	abstract public function getOrder();

	public function getGatewayExtra()
	{
		return $this->gatewayExtraJson ? json_decode($this->gatewayExtraJson, true) : [];
	}

	public function setGatewayExtra($data)
	{
		$this->gatewayExtraJson = json_encode($data);
	}

}
