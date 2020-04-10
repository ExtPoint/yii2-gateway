<?php

namespace gateway\tests\unit\helpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppTestCase extends TestCase {

    protected function createSafeMock(string $originalClassName): MockObject
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->disableAutoReturnValueGeneration()
            ->getMock();
    }

    protected function createModelMock(string $className, array $attributes): MockObject {
        $mock = $this->createSafeMock($className);

        $mock->method('save')->willReturn(true);
        $mock->method('attributes')->willReturn(array_keys($attributes));
        $mock->method('__get')->willReturnMap(array_map(
            function ($name, $value) { return [$name, $value]; },
            array_keys($attributes),
            $attributes
        ));

        return $mock;
    }

    protected function mockStaticClass(string $className): MockObject {
        $o = $this->createSafeMock($className . 'MockStatic');
        $className::$mock = $o;
        return $o;
    }
}
