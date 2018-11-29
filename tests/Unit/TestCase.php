<?php
namespace Mongolid;

use Illuminate\Container\Container as IlluminateContainer;
use Mockery as m;
use Mongolid\Container\Container;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionClass;
use ReflectionMethod;

class TestCase extends PHPUnitTestCase
{
    protected function setUp()
    {
        Container::setContainer(new IlluminateContainer());
    }

    protected function tearDown()
    {
        Container::flush();
        m::close();
        parent::tearDown();
    }

    /**
     * Actually runs a protected method of the given object.
     *
     * @param object $obj
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function callProtected($obj, $method, $args = [])
    {
        $methodObj = new ReflectionMethod(get_class($obj), $method);
        $methodObj->setAccessible(true);

        return $methodObj->invokeArgs($obj, $args);
    }

    /**
     * Set a protected property of an object.
     *
     * @param mixed  $obj      object Instance
     * @param string $property property name
     * @param mixed  $value    value to be set
     */
    protected function setProtected($obj, $property, $value): void
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($obj, $value);
    }

    /**
     * Get a protected property of an object.
     *
     * @param mixed  $obj      object Instance
     * @param string $property property name
     *
     * @return mixed property value
     */
    protected function getProtected($obj, $property)
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    /**
     * Replace instance on Ioc
     */
    protected function instance($abstract, $instance)
    {
        Container::bind(
            $abstract,
            function () use ($instance) {
                return $instance;
            }
        );

        return $instance;
    }
}