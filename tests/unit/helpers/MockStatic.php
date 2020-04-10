<?php

namespace gateway\tests\unit\helpers;

use PHPUnit\Framework\MockObject\MockObject;

class MockStatic {
    /** @var MockObject */
    public static $mock;

    public static function __callStatic($name, $arguments)
    {
        return static::$mock->{$name}(...$arguments);
    }

    public static function installMock(string $targetClassName, array $publicStaticMethods) {
        $pos = strrpos($targetClassName, '\\');
        $class = substr($targetClassName, $pos + 1);
        $namespace = trim(substr($targetClassName, 0, $pos), '\\');
        $methods = '';
        foreach ($publicStaticMethods as $method) {
            $methods .= "public function $method() {}\n";
        }
        eval("
            namespace $namespace;
            class $class extends \\gateway\\tests\\unit\\helpers\\MockStatic {
                public static \$mock;
            }

            class ${class}MockStatic {
                $methods
            }
        ");
    }
}
