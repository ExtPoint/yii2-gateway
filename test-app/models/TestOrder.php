<?php
namespace app\models;

use gateway\models\Order;

class TestOrder extends Order {

    public function attributes()
    {
        return [
            'id',
            'state',
            'initialAmount',
            'recurringAmount',
            'recurringPeriodName',
            'recurringPeriodScale',
            'gatewayName',
            'gatewayParamsJson',
            'slug', // TODO: Clean this
        ];
    }

    public function getTitle()
    {
        return 'Sample Product';
    }

    public function getTransactions()
    {
        return [];
    }
}
