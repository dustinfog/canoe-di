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
     * @param string $id
     * @return static
     */
    public static function getInstance($id = null)
    {
        if (empty($id)) {
            return Context::get(static::class);
        }

        $value = Context::get($id);
        if (!($value instanceof static)) {
            $class = static::class;
            throw new \InvalidArgumentException("not a $class instance");
        }

        return $value;
    }
}