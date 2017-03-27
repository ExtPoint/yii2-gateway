<?php
namespace gateway\models;

use gateway\exceptions\GatewayException;
use yii\db\ActiveRecord;

/**
 * Db attributes
 * @property string|int $id
 * @property float $initialAmount
 * @property float $recurringAmount
 *
 * Dynamic in library, but could be by database in app
 * @property string $title Item description
 * @property float $gatewayInitialAmount
 * @property float $gatewayRecurringAmount
 *
 * Dynamic attributes and properties
 * @property string[] $gatewayParams
 * @property Order $order
 */
abstract class OrderItem extends ActiveRecord
{
	abstract public function getOrder();

	public function getTitle()
	{
		throw new GatewayException('Implement this in user code');
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
