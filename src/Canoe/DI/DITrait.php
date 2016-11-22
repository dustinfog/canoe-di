<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 9/10/16
 * Time: 6:51 PM
 */

namespace Canoe\DI;

use Canoe\Utils\DocProperty;

/**
 * Class DITrait
 * @package Canoe
 */
trait DITrait
{
    /**
     * @param string $name
     * @param mixed  $value
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        $property = DocProperty::get(self::class, $name);
        if ($property != null) {
            if ($property->getAccess() == DocProperty::ACC_READ) {
                throw new \Exception("cannot write read only property ".__CLASS__."::$name");
            }

            if (!$property->isValueAcceptable($value)) {
                $typeSpec = $property->getTypeSpec();
                throw new \Exception("assign ".__CLASS__."::$name error: $typeSpec required");
            }
        }

        $this->$name = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     * @throws \Exception
     */
    public function __get($name)
    {
        $property = DocProperty::get(self::class, $name);
        if (!empty($property)) {
            $value = $this->wire($property);
            $this->$name = $value;

            return $value;
        }

        return null;
    }

    private function wire(DocProperty $property)
    {
        if (empty($property)) {
            return null;
        }

        $name = $property->getName();
        if ($property->getAccess() == DocProperty::ACC_WRITE) {
            throw new \Exception("cannot read write only property $name");
        }

        $uses = $property->getUses();

        if ($uses) {
            $value = Context::get($uses);
            if ($value == null) {
                throw new \Exception("cannot find a bean with name $uses for $name");
            }

            if (!$property->isValueAcceptable($value)) {
                throw new \Exception("value is not a acceptable value for property $name");
            }

            return $value;
        }

        $type = $property->getType();
        if ($property->getArrayDimension() == 0 && class_exists($type)) {
            return Context::get($type);
        }

        throw new \Exception("cannot wire $name");
    }
}