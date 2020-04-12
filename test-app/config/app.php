<?php

return [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'components' => [
        'request' => [
            'cookieValidationKey' => 'dev-mode',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '/' => '/frame/index'
            ],
        ],
        'i18n' => [
            'translations' => [
                '*' => [
                    'class' => 'yii\i18n\MessageSource',
                ],
            ],
        ],
    ],
    'modules' => [
        'gateway' => [
            'class' => '\gateway\GatewayModule',

            'orderClassName' => '\app\models\TestOrder',
            'transactionClassName' => '\app\models\TestTransaction',
            'callbackLogEntryClassName' => '\app\models\TestCallbackLogEntry',

            'successUrl' => ['/frame/success'],
            'failureUrl' => ['/frame/failure'],
            // callbackUrl = default

            'gateways' => [
                'paypal' => require(__DIR__ . '/paypal.php'),
            ],
        ],
    ],
];
