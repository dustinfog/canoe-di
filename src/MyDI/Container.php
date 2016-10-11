<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 10/10/2016
 * Time: 11:51 AM
 */

namespace MyDI;

/**
 * Class Container
 * @package MyDI
 */
class Container
{
    private static $entries = array();
    private static $beans = array();

    /**
     * @param callable $callback
     * @param string   $id
     */
    public static function registerCallback(callable $callback, $id)
    {
        if (empty($id) || !is_string($id)) {
            throw new \InvalidArgumentException("invalid id");
        }

        $entry = new RegisterEntry(RegisterEntry::TYPE_CALLBACK, $$callback);
        self::$entries[$id] = $entry;
    }

    /**
     * @param string      $class
     * @param string|null $id
     */
    public static function registerClass($class, $id = null)
    {
        if (empty($class) || !is_string($class) || !class_exists($class)) {
            throw new \InvalidArgumentException("invalid class");
        }

        if (!empty($id)) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException("invalid id");
            }

            if (class_exists($id) && ( $id != $class || !is_subclass_of($class, $id))) {
                throw new \InvalidArgumentException("$class is not a subclass of $id");
            }
        }

        $entry = new RegisterEntry(RegisterEntry::TYPE_CLASS, $class);
        self::autoRegisterClassEntry($entry);

        if (!empty($id)) {
            self::$entries[$id] = $entry;
        }
    }

    /**
     * @param string $id
     * @param mixed  $value
     */
    public static function set($id, $value)
    {
        if (empty($id) || !is_string($id)) {
            throw new \InvalidArgumentException('invalid id');
        }

        if (class_exists($id) && !($value instanceof $id)) {
            throw new \InvalidArgumentException('bean is not a instance of $id');
        }

        if (is_object($value)) {
            self::autoRegisterBean($value);
        }

        self::$beans[$id] = $value;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public static function get($id)
    {
        if (isset(self::$beans[$id])) {
            return self::$beans[$id];
        } else {
            $bean = null;
            if (isset(self::$entries[$id])) {
                $bean = self::createFromEntry(self::$entries[$id]);
                self::set($id, $bean);
            } elseif (class_exists($id)) {
                $bean = self::createFromClass($id);
                self::set($id, $bean);
            }

            return $bean;
        }
    }

    private static function autoRegisterClassEntry(RegisterEntry $entry)
    {
        if ($entry->getType() == RegisterEntry::TYPE_CLASS) {
            $class = $entry->getSpec();
            $parentClass = $class;
            while ($parentClass != null) {
                if (!isset(self::$entries[$parentClass])) {
                    self::$entries[$parentClass] = $entry;
                }

                $parentClass = get_parent_class($parentClass);
            }

            foreach (class_implements($class) as $interface) {
                if (!isset(self::$entries[$interface])) {
                    self::$entries[$interface] = $entry;
                }
            }
        }
    }

    private static function autoRegisterBean($bean)
    {
        if (!is_object($bean)) {
            return;
        }

        $class = get_class($bean);
        $parentClass = $class;
        while ($parentClass != null) {
            if (!isset(self::$beans[$parentClass])) {
                self::$beans[$parentClass] = $bean;
            }

            $parentClass = get_parent_class($parentClass);
        }

        foreach (class_implements($class) as $interface) {
            if (!isset(self::$beans[$interface])) {
                self::$beans[$interface] = $bean;
            }
        }
    }

    private static function createFromEntry(RegisterEntry $entry)
    {
        if ($entry->getType() == RegisterEntry::TYPE_CALLBACK) {
            $callback = $entry->getSpec();

            return $callback($entry);
        }

        return self::createFromClass($entry->getSpec());
    }

    private static function createFromClass($className)
    {
        $class = new \ReflectionClass($className);
        $constructor = $class->getConstructor();

        if ($constructor == null) {
            return $class->newInstance();
        }

        $formalParameters = $constructor->getParameters();
        $actualParameters = [];

        foreach ($formalParameters as $parameter) {
            $parameterName = $parameter->getName();
            $actualParameter = self::get($parameterName);
            if ($actualParameter == null && !empty($parameterClass = $parameter->getClass())) {
                $actualParameter = self::get($parameterClass->getName());
            }

            if (empty($actualParameter)) {
                throw new \InvalidArgumentException("create $class instance failed: can't find a bean with id [$parameterName]");
            }

            $actualParameters[] = $actualParameter;
        }

        return $class->newInstanceArgs($actualParameters);
    }
}