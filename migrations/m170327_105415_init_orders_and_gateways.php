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
            'state' => "enum('ready', 'complete', 'cancelled') NOT NULL DEFAULT 'ready'",
            'initialAmount' => $this->money($moneyPrecision)->notNull()->defaultValue(0),
            'recurringAmount' => $this->money($moneyPrecision)->notNull()->defaultValue(0),
            'recurringPeriodName' => "enum('day', 'week', 'month', 'year') NULL DEFAULT NULL",
            'recurringPeriodScale' => $this->integer()->notNull()->defaultValue(0),
            'gatewayName' => $this->string(32),
            'gatewayParamsJson' => "longtext NULL",
        ]);

        $this->createTable('gateways_callback_log', [
            'id' => 'pk',
            'requestDump' => "longtext NOT NULL",
            'responseDump' => "longtext NULL",
        ]);

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
            'sum' => $this->money($moneyPrecision)->null(),
            'notes' => "longtext NULL",
            'gatewayExtraJson' => "longtext NULL",
        ]);

        $this->addForeignKey('fk_order', 'gateways_transactions', 'orderId', 'gateways_orders', 'id');
        $this->addForeignKey('fk_log', 'gateways_transactions', 'logId', 'gateways_callback_log', 'id');
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
