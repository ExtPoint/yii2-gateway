<?php
namespace gateway\models;

use yii\db\ActiveRecord;

/**
 * @property float $gatewayAmount
 * @property int $id
 * @property string $requestDump
 * @property string|null $responseDump
 */
class CallbackLogEntry extends ActiveRecord
{
	public function setRequest($data)
	{
		$this->requestDump = var_export($data, true);
	}

	public function setResponse($data)
	{
		$this->responseDump = var_export($data, true);
	}
}
