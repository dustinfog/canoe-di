<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 9/10/16
 * Time: 6:47 PM
 */

namespace Canoe;

/**
 * Class PropertyParser
 * @package Canoe
 */
class PropertyParser
{
    private function __construct()
    {
    }

    private static $classPropertiesMap = array();

    /**
     * @param string $className
     * @return Property[]
     */
    public static function parse($className)
    {
        if (empty($className)) {
            return null;
        }

        if (isset(self::$classPropertiesMap[$className])) {
            return self::$classPropertiesMap[$className];
        }

        if (!class_exists($className)) {
            return null;
        }

        $class = new \ReflectionClass($className);
        $comments = $class->getDocComment();
        $lines = explode(PHP_EOL, $comments);

        $properties = array();
        foreach ($lines as $line) {
            if (preg_match(
                '/\*\s*@property-?([^\s]*)\s+([^\s]*)\s*\$([^\s]*)/',
                $line,
                $matches
            )) {
                $property = new Property();
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