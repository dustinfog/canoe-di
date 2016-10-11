<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 11/10/2016
 * Time: 11:16 AM
 */

namespace MyDI;

/**
 * Class RegisterType
 * @package MyDI
 */
class RegisterType
{
    const TYPE_VALUE = 1;
    const TYPE_CALLBACK = 2;
    const TYPE_CLASS = 3;

    /**
     * @param int   $type
     * @param mixed $value
     */
    public static function validate($type, $value)
    {
        if (!in_array($type, [self::TYPE_VALUE, self::TYPE_CLASS, self::TYPE_CALLBACK])) {
            throw new \InvalidArgumentException('invalid type, type must be one of RegisterType::TYPE_VALUE, RegisterType::TYPE_CLASS and  RegisterType::TYPE_CALLBACK]');
        }

        if ($type == self::TYPE_CLASS && (!is_string($value) || !class_exists($value))) {
            throw new \InvalidArgumentException('invalid class');
        } elseif ($type == self::TYPE_CALLBACK && !is_callable($value)) {
            throw new \InvalidArgumentException('invalid callable');
        }
    }

    /**
     * RegisterType constructor.
     */
    private function __construct()
    {

    }
}
