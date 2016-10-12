<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 9/10/16
 * Time: 6:51 PM
 */

namespace Canoe;

/**
 * Class IocSupporter
 * @package Canoe
 */
trait CanoeDITrait
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

        $instance = CanoeDI::get($name);
        $type = $properties[$name]->type;
        if (!($instance instanceof $type)) {
            $instance = CanoeDI::get($type);
        }

        return $instance;
    }
}