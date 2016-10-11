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
     * @param mixed       $spec
     * @param int         $type
     * @param string|null $id
     */
    public static function register($spec, $type, $id = null)
    {
        if (($type == RegisterType::TYPE_VALUE && (is_scalar($spec))
                || $type == RegisterType::TYPE_CALLBACK)
            && empty($id)) {
            throw new \InvalidArgumentException("can not register a scalar or a callback value without specifying id");
        }

        if (!empty($id)) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException("id must be a string");
            }

            if (class_exists($id)
                && $type == RegisterType::TYPE_CLASS
                && ( $id != $spec || !is_subclass_of($spec, $id))) {
                throw new \InvalidArgumentException("$spec is not a subclass of $id");
            }
        }

        if ($type == RegisterType::TYPE_VALUE) {
            self::$beans[$id] = $spec;

            self::autoRegisterBean($spec);

            return;
        }

        $entry = new RegisterEntry($type, $spec);
        self::autoRegisterClassEntry($entry);
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

                if (class_exists($id) && !($bean instanceof $id)) {
                    throw new \RuntimeException('bean is not a instance of $id');
                }


                self::$beans[$id] = $bean;
            }

            if (class_exists($id)) {
                $bean = self::createFromClass($id);
                self::$beans[$id] = $bean;
            }

            return $bean;
        }
    }

    private static function autoRegisterClassEntry(RegisterEntry $entry)
    {
        if ($entry->getType() == RegisterType::TYPE_CLASS) {
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
        while ($class != null) {
            if (!isset(self::$entries[$class])) {
                self::$beans[$class] = $bean;
            }

            $class = get_parent_class($class);
        }

        foreach (class_implements($class) as $interface) {
            if (!isset(self::$entries[$interface])) {
                self::$beans[$interface] = $bean;
            }
        }
    }

    private static function createFromEntry(RegisterEntry $entry)
    {
        if ($entry->getType() == RegisterType::TYPE_CALLBACK) {
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