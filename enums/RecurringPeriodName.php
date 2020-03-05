<?php

namespace gateway\enums;

use extpoint\yii2\base\Enum;

abstract class RecurringPeriodName extends Enum
{
    const DAY = 'day';
    const WEEK = 'week';
    const MONTH = 'month';
    const YEAR = 'year';

    // TODO: Use better enum
    static function getKeys() {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }
}
