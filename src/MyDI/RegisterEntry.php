<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 11/10/2016
 * Time: 11:26 AM
 */

namespace MyDI;

/**
 * Class RegisterEntry
 * @package MyDI
 */
class RegisterEntry
{
    const TYPE_VALUE = 1;
    const TYPE_CALLBACK = 2;
    const TYPE_CLASS = 3;

    private $type;
    private $spec;

    /**
     * RegisterEntry constructor.
     * @param int   $type
     * @param mixed $spec
     * @internal
     */
    public function __construct($type, $spec)
    {
        $this->type = $type;
        $this->spec = $spec;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getSpec()
    {
        return $this->spec;
    }
}