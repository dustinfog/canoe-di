<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 10/10/2016
 * Time: 11:51 AM
 */

namespace Canoe\DI;

/**
 * Class Context
 * @package Canoe
 */
class Context
{
    private static $definitions = array();
    private static $beans = array();

    /**
     * $config is a array with schema like follow:
     *
     * [
     *  'definitions' => [
     *      'id1' => function() {},
     *      'id2' => 'ClassName1',
     *      'ClassName2'
     *  ],
     *  'beans' => [
     *      'id3' => $value
     *  ]
     * ]
     *
     * @param array $config
     */
    public static function loadConfig(array $config)
    {
        if (isset($config['beans'])) {
            foreach ($config['beans'] as $key => $value) {
                if (!is_numeric($key)) {
                    self::set($key, $value);
                } else {
                    throw new \UnexpectedValueException('invalid key for bean');
                }
            }
        }

        if (isset($config['definitions'])) {
            foreach ($config['definitions'] as $key => $definition) {
                self::registerDefinition($definition, is_numeric($key) ? null : $key);
            }
        }
    }

    /**
     * @param string          $id
     * @param string|callable $definition
     */
    public static function registerDefinition($id, $definition)
    {
        if ($id == $definition) {
            return;
        }

        if (empty($id) || !is_string($id)) {
            throw new \InvalidArgumentException("invalid id $id");
        }

        if (is_callable($definition)) {
            self::$definitions[$id] = $definition;

            return;
        }

        if (is_string($definition) && class_exists($definition)) {
            if (class_exists($id) && !is_subclass_of($definition, $id)) {
                throw new \InvalidArgumentException("$definition is not a subclass of $id");
            }
            self::$definitions[$id] = $definition;

            return;
        }

        throw new \InvalidArgumentException("invalid definition for $id");
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
        }

        $bean = null;
        if (isset(self::$definitions[$id])) {
            $bean = self::createFromDefinition(self::$definitions[$id]);
            self::set($id, $bean);
        } elseif (class_exists($id)) {
            $bean = self::createFromClass($id);
            self::set($id, $bean);
        }

        return $bean;
    }

    private static function createFromDefinition($definition)
    {
        if (is_callable($definition)) {
            return $definition();
        }

        return self::createFromClass($definition);
    }

    private static function createFromClass($className)
    {
        $class = new \ReflectionClass($className);
        $constructor = $class->getConstructor();

        if ($constructor == null || $constructor->getNumberOfParameters() == 0) {
            return $class->newInstance();
        }

        throw new \InvalidArgumentException("number of parameters is not 0, create $className instance failed. try to set a bean or register a callback definition");
    }
}