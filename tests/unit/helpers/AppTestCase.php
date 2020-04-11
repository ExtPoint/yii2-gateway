<?php

namespace gateway\tests\unit\helpers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppTestCase extends TestCase
{
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

    protected function createModelMock(string $className, array $attributes): MockObject
    {
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

    private static $lastMockId = 0;

    protected function createStaticClassMock(array $methods): string
    {
        $className = '\gateway\tests\unit\mocks\Mock' . (++self::$lastMockId);
        MockStatic::installMock($className, array_keys($methods));
        $o = $this->mockStaticClass($className);
        foreach ($methods as $method => $return) {
            $o->method($method)->willReturn($return);
        }
        return $className;
    }

    protected function mockStaticClass(string $className): MockObject
    {
        $o = $this->createSafeMock($className . 'MockStatic');
        $className::$mock = $o;
        return $o;
    }

    protected function readJsonFixture($filename)
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../fixtures/' . $filename),
            true
        );
    }

    protected function trackCalls(MockObject $object, string $method, &$calls)
    {
        $calls = [];
        $object->method($method)->willReturnCallback(
            function (...$arguments) use (&$calls) {
                $calls[] = $arguments;
            }
        );
    }

    protected function trackMagicProperties(MockObject $object, &$result)
    {
        $result = [];
        $object->method('__set')->willReturnCallback(
            function ($name, $value) use (&$result) {
                $result[$name] = $value;
            }
        );
    }
}
