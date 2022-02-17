<?php /** @noinspection PhpUnusedPrivateFieldInspection */

namespace DigiLive\Reflector;

use ReflectionClass;
use ReflectionException;

/**
 * Wrapper class around Reflection class to access private or protected method and properties of another class in a
 * more convenient way.
 *
 * PHP version 7.4 or greater
 *
 * @package         DigiLive\Reflector
 * @author          Ferry Cools <info@DigiLive.nl>
 * @copyright   (c) 2022 Ferry Cools
 * @version         1.0.0
 * @license         New BSD License http://www.opensource.org/licenses/bsd-license.php
 * @todo            Get this package from composer.
 */
class Reflector
{
    /**
     * @var Reflector|null First created instance of this class by method create.
     * @see Reflector::create()
     */
    private static ?Reflector $instance = null;
    /**
     * @var object The object to reflect.
     */
    private static object $object;

    /**
     * @var ReflectionClass Reflection of the class from the reflected object.
     */
    private ReflectionClass $reflectedClass;
    /**
     * @var object Instance of the object from which a reflection class is constructed.
     */
    private object $reflectedObject;

    /**
     * Construct a reflection class for a given object.
     *
     * @param   object  $object  Instance of the class to reflect.
     */
    private function __construct(object $object)
    {
        $this->reflectedObject = $object;
        $this->reflectedClass  = new ReflectionClass($object);
    }

    /**
     * Create a reflection class for a given object.
     *
     * If the reflection class already exist for this object, don't create a new one, but return the existing one
     * instead.
     *
     * @param   object  $object  The object to reflect.
     *
     * @return Reflector|null The reflected object.
     */
    public static function create(object $object): ?Reflector
    {
        if (self::$instance === null || self::$instance::$object !== $object) {
            self::$instance          = new self($object);
            self::$instance::$object = $object;
        }

        return self::$instance;
    }

    /**
     * Unset the class reflection.
     *
     * @return object The object the reflection class was created for.
     * @noinspection PhpUndefinedVariableInspection
     */
    public function unset(): object
    {
        $object         = self::$instance::$object;
        self::$instance = null;

        return $object;
    }

    /**
     * Get the value of a private or protected property.
     *
     * @param   string  $propertyName  Name of the property to set.
     *
     * @throws ReflectionException If no property exists by that name.
     */
    public function __get(string $propertyName)
    {
        $property = $this->reflectedClass->getProperty($propertyName);

        $property->setAccessible(true);

        return $property->getValue($this->reflectedObject);
    }

    /**
     * Set the value of a private or protected property.
     *
     * @param   string  $propertyName  Name of the property to set.
     * @param   mixed   $value         Value to set.
     *
     * @throws ReflectionException If no property exists by that name.
     */
    public function __set(string $propertyName, $value): void
    {
        $property = $this->reflectedClass->getProperty($propertyName);

        $property->setAccessible(true);

        $property->setValue($this->reflectedObject, $value);
    }

    /**
     * Invoke a private or protected method.
     *
     * @param   string  $methodName  Name of the method to invoke.
     * @param   array Parameter values of the method to invoke.
     *
     * @throws ReflectionException If the method does not exist.
     */
    public function __call(string $methodName, array $parameters = [])
    {
        $method = $this->reflectedClass->getMethod($methodName);

        $method->setAccessible(true);

        return $method->invoke($this->reflectedObject, ...$parameters);
    }
}
