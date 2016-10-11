<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 9/10/16
 * Time: 6:51 PM
 */

namespace MyDI;

/**
 * Class IocSupporter
 * @package MyDI
 */
trait DITrait
{
    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        $properties = PropertyParser::parse(self::class);
        if (!isset($properties[$name])) {
            return null;
        }

        $instance = Container::get($name);
        $type = $properties[$name]->type;
        if (!($instance instanceof $type)) {
            $instance = Container::get($type);
        }

        return $instance;
    }
}