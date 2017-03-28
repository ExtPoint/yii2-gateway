<?php

namespace gateway\enums;

abstract class OrderState
{
    /**
     * Ожидаем оплаты
     */
    const READY = 'ready';

    /**
     * Успех
     */
    const COMPLETE = 'complete';

    /**
     * Невосстановимая проблема, либо отмена операции на стороне платёжной системы
     */
    // Не бывает так. Не получилось - пытаемся ещё раз, пока не CANCELLED // const FAILED = 'failed';

    /**
     * Устарел или отменён на стороне приложения
     */
    const CANCELLED = 'cancelled';

    // TODO: Use better enum
    static function getKeys() {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }
}