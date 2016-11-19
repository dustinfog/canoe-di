<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 19/11/2016
 * Time: 10:54 PM
 */

namespace Canoe\DI;

trait SingletonTrait
{
    /**
     * @return static
     */
    public static function getInstance()
    {
        return Context::get(static::class);
    }
}