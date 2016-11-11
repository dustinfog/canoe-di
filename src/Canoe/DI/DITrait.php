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
     * @return mixed|null
     */
    public function __get($name)
    {
        $properties = DocProperty::parse(self::class);
        if (!isset($properties[$name])) {
            return null;
        }

        $instance = Context::get($name);
        $type = $properties[$name]->getType();
        if (!($instance instanceof $type)) {
            $instance = Context::get($type);
        }

        return $instance;
    }
}