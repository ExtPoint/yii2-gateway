<?php
namespace gateway\models;

use extpoint\yii2\base\Model;

/**
 * @property float $gatewayAmount
 * @property int $id
 * @property string $time
 * @property float|null $duration
 * @property string $requestDump
 * @property string|null $responseDump
 */
class CallbackLogEntry extends Model
{
    public function setRequest($data)
    {
        try {
            $requestDump = var_export($data, true);
        }
        catch (\Throwable $e) {
            $requestDump = print_r($data, true);
        }
        $this->requestDump = $requestDump;
    }

    public function setResponse($data)
    {
        try {
            $responseDump = var_export($data, true);
        }
        catch (\Throwable $e) {
            $responseDump = print_r($data, true);
        }
        $this->responseDump = $responseDump;
    }
}
