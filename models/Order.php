<?php
namespace gateway\models;

use gateway\enums\OrderState;
use gateway\enums\RecurringPeriodName;
use gateway\exceptions\GatewayException;
use yii\db\ActiveRecord;

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
 * @property string $title Short description
 * @property string $description Complete description
 * @property int $trialDays
 * @property float $gatewayInitialAmount
 * @property float $gatewayRecurringAmount
 *
 * Dynamic attributes and properties
 * @property string[] $gatewayParams
 * @property OrderItem[]|null $items
 * @property Transaction[] $transactions
 */
abstract class Order extends ActiveRecord
{
	public function rules()
	{
		return [
			// Defaults
			['state', 'default', 'value' => OrderState::READY],
			['initialAmount', 'default', 'value' => 0],
			['recurringAmount', 'default', 'value' => 0],

			// Types
			['state', 'range', 'in' => OrderState::getKeys()],
			['recurringPeriodName', 'range', 'in' => RecurringPeriodName::getKeys()],

			// Complex logic
			['state', function() {
				if ($this->isAttributeChanged('state') && $this->getOldAttribute('state') !== OrderState::READY) {
					$this->addError('state', \Yii::t('yii2-gateways', 'Order cannot be reverted from terminal states'));
				}
			}],
			['initialAmount', function() {
				$items = $this->items;
				if ($items) {
					$sum = 0;
					foreach ($items as $item) {
						$sum += $item->initialAmount;
					}
					if ($sum !== $this->initialAmount) {
						$this->addError('initialAmount', \Yii::t('yii2-gateways', 'Initial amount or order is not equal the sum of initial amounts of its items'));
					}
				}
			}],
			['recurringAmount', function() {
				$items = $this->items;
				if ($items) {
					$sum = 0;
					foreach ($items as $item) {
						$sum += $item->recurringAmount;
					}
					if ($sum !== $this->recurringAmount) {
						$this->addError('recurringAmount', \Yii::t('yii2-gateways', 'Recurring amount or order is not equal the sum of recurring amounts of its items'));
					}
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

	abstract public function getTransactions();

	public function getGatewayParams()
	{
		return $this->gatewayParamsJson ? json_decode($this->gatewayParamsJson, true) : [];
	}

	public function setGatewayParams($data)
	{
		$this->gatewayParamsJson = json_encode($data);
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
}
