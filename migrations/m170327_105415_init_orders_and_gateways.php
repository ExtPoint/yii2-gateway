<?php

use yii\db\Migration;

class m170327_105415_init_orders_and_gateways extends Migration
{
    public function up()
    {
        $moneyPrecision = 2; // 8 for Bitcoins

        $this->createTable('gateways_orders', [
            'id' => 'pk',
            // Suggested // 'userId' => 'int NOT NULL',
            'state' => \gateway\enums\OrderState::toMysqlEnum('ready'),
            'initialAmount' => $this->money(19, $moneyPrecision)->notNull()->defaultValue(0),
            'recurringAmount' => $this->money(19, $moneyPrecision)->notNull()->defaultValue(0),
            'recurringPeriodName' => "enum('day', 'week', 'month', 'year') NULL DEFAULT NULL",
            'recurringPeriodScale' => $this->integer()->notNull()->defaultValue(0),
            'gatewayName' => $this->string(32),
            'gatewayParamsJson' => "longtext NULL",
            // Suggested // 'createdAt' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            // Suggested // 'updatedAt' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ], "CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB");

        /* Optional:
        $this->createTable('gateways_order_items', [
            'id' => 'pk',
            'orderId' => 'int NOT NULL',

            // Good description. Could be very different
            'title' => "string NOT NULL DEFAULT ''",
            'goodId' => "int NOT NULL",
            'amount' => "int NOT NULL DEFAULT 1",
            'addOns' => "longtext NULL",

            'initialAmount' => $this->money($moneyPrecision)->notNull()->defaultValue(0),
            'recurringAmount' => $this->money($moneyPrecision)->notNull()->defaultValue(0),
            'createdAt' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updatedAt' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ], "CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB");*/

        $this->createTable('gateways_callback_log', [
            'id' => 'pk',
            'at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'requestDump' => "longtext NOT NULL",
            'responseDump' => "longtext NULL",
            'duration' => 'double NULL',
        ], "CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB");

        $this->createTable('gateways_transactions', [
            'id' => 'pk',
            'kind' => $this->string(32)->notNull(), /* Or:
                enum('subscriptionStarted', 'subscriptionUpdated', 'subscriptionCancelled', 'paymentReceived',
                    'paymentRefunded', 'payoffMade', 'invoiceCreated', 'invoiceUpdated', 'invoiceDeleted',
                    'invoicePaid', 'invoiceFailed', 'orderCheck', 'generic'
                ) NOT NULL
            */
            'orderId' => $this->integer()->null(),
            'logId' => $this->integer()->null(),
            'externalEventId' => $this->string()->null(),
            'externalSubscriptionId' => $this->string()->null(),
            'externalInvoiceId' => $this->string()->null(),
            'sum' => $this->money(19, $moneyPrecision)->null(),
            'billingPeriodStartDate' => $this->date(),
            'billingPeriodEndDate' => $this->date(),
            'notes' => "longtext NULL",
            'gatewayExtraJson' => "longtext NULL",
        ], "CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB");

        $this->addForeignKey('fk__gateways_transactions__orderId', 'gateways_transactions', 'orderId', 'gateways_orders', 'id');
        $this->addForeignKey('fk__gateways_transactions__logId', 'gateways_transactions', 'logId', 'gateways_callback_log', 'id');
        $this->createIndex('orderId', 'gateways_transactions', 'orderId');
        $this->createIndex('externalSubscriptionId', 'gateways_transactions', 'externalSubscriptionId');
        $this->createIndex('externalInvoiceId', 'gateways_transactions', 'externalInvoiceId');
    }

    public function down()
    {
        $this->dropTable('gateways_transactions');
        $this->dropTable('gateways_callback_log');
        $this->dropTable('gateways_orders');
    }
}
