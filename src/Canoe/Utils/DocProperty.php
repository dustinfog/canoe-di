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
    const ACC_WRITE = 'write';
    const ACC_READ = 'read';
    const ACC_ALL = '';

    private $access;
    private $type;
    private $name;
    private $arrayDimension = 0;
    private $uses;
    private $typeSpec;

    private static $classPropertiesMap = array();

    private function __construct()
    {
    }

    /**
     *
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
     * @return int
     */
    public function getArrayDimension()
    {
        return $this->arrayDimension;
    }

    /**
     * @return string
     */
    public function getUses()
    {
        return $this->uses;
    }

    /**
     * @return string
     */
    public function getTypeSpec()
    {
        return $this->typeSpec;
    }

    /**
     * @param mixed $value
     * @return boolean
     */
    public function isValueAcceptable($value)
    {
        if ($value === null) {
            return true;
        }

        if (empty($this->type)) {
            return true;
        }

        if ($this->arrayDimension > 0) {
            return is_array($value);
        }

        if (class_exists($this->type) || interface_exists($this->type)) {
            return $value instanceof $this->type;
        }

        return gettype($value) == $this->type;
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

    /**
     * @param string $className
     * @param string $name
     * @return DocProperty|null
     */
    public static function get($className, $name)
    {
        $properties = self::parse($className);
        if ($properties == null || !isset($properties[$name])) {
            return null;
        }

        return $properties[$name];
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
                '/\*\s*@property-?([^\s]*)\s+([^\s]*)\s*\$([^\s]*)\s*(.*)/',
                $line,
                $matches
            )) {
                $arrayInfo = self::parseArray($matches[2]);

                $property = new DocProperty();
                $property->access = $matches[1];
                $property->typeSpec = $matches[2];
                $property->type = self::tryIntegrateClassName($className, $arrayInfo[0]);
                $property->arrayDimension = $arrayInfo[1];
                $property->name = $matches[3];
                $property->uses = self::parseUses($matches[4]);

                $properties[$property->name] = $property;
            }
        }

        self::$classPropertiesMap[$className] = $properties;

        return $properties;
    }

    private static function parseUses($desc)
    {
        if (preg_match("/\\{\\s*@uses\\s+([^\\s\\}]+)/", $desc, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function parseArray($name)
    {
        $tokenPos = strpos($name, '[');
        if ($tokenPos === false) {
            return [$name, 0];
        }

        $dimension = 0;
        $valid = false;
        for ($i = $tokenPos + 1; $i < strlen($name); $i ++) {
            if ($name[$i] == ']' && !$valid) {
                $valid = true;
                $dimension ++;
            } elseif ($name[$i] == '[' && $valid) {
                $valid = false;
            } else {
                $valid = false;
                break;
            }
        }

        if (!$valid) {
            throw new \Exception("invalid type identifier $name");
        }

        return [substr($name, 0, $tokenPos), $dimension];
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