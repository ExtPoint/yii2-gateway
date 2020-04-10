<?php

use gateway\tests\unit\helpers\MockStatic;

MockStatic::installMock('\yii\helpers\Url', [
    'toRoute',
    'to',
    'ensureScheme',
    'base',
    'remember',
    'previous',
    'canonical',
    'home',
    'isRelative',
    'current',
]);
