<?php

namespace gateway\enums;

abstract class OrderState
{
    /** Waiting for payment */
    const READY = 'ready';

    /** Success */
    const COMPLETE = 'complete';

    /** Subscription is started and not yet cancelled */
    const SUBSCRIPTION_ACTIVE = 'subscriptionActive';

    /**
     * Невосстановимая проблема, либо отмена операции на стороне платёжной системы
     */
    // Не бывает так. Не получилось - пытаемся ещё раз, пока не CANCELLED // const FAILED = 'failed';

    /** Expired, or cancelled by the app, or it was a subscription and it is cancelled by user */
    const CANCELLED = 'cancelled';

    // TODO: Use better enum
    static function getKeys() {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }
}
