<?php

/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 9/10/16
 * Time: 6:46 PM
 */
namespace Canoe\Utils;

/**
 * Class DocProperty
 * @package Canoe
 */
class DocProperty
{
    private $access;
    private $type;
    private $name;

    private static $classPropertiesMap = array();

    private function __construct()
    {
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $className
     * @return DocProperty[]
     */
    public static function parse($className)
    {
        if (!class_exists($className)) {
            return null;
        }

        return self::parseClass(new \ReflectionClass($className));
    }

    private static function parseClass(\ReflectionClass $class)
    {
        $className = $class->getName();

        if (isset(self::$classPropertiesMap[$className])) {
            return self::$classPropertiesMap[$className];
        }

        $parent = $class->getParentClass();
        if ($parent) {
            $properties = self::parseClass($parent);
        } else {
            $properties = array();
        }

        $comments = $class->getDocComment();
        $lines = explode(PHP_EOL, $comments);

        foreach ($lines as $line) {
            if (preg_match(
                '/\*\s*@property-?([^\s]*)\s+([^\s]*)\s*\$([^\s]*)/',
                $line,
                $matches
            )) {
                $property = new DocProperty();
                $property->access = $matches[1];
                $property->type = self::tryIntegrateClassName($className, $matches[2]);
                $property->name = $matches[3];
                $properties[$property->name] = $property;
            }
        }

        self::$classPropertiesMap[$className] = $properties;

        return $properties;
    }

    private static function tryIntegrateClassName($ownerClassName, $type)
    {
        if (class_exists($type)) {
            return $type;
        }

        if (strpos($type, '\'') === false) {
            $class = self::parseNamespace($ownerClassName).$type;
            if (class_exists($class)) {
                return $class;
            }
        }

        return $type;
    }

    private static function parseNamespace($className)
    {
        $lastPos = strrpos($className, '\\');
        if ($lastPos !== false) {
            return substr($className, 0, $lastPos + 1);
        }

        return '';
    }
}