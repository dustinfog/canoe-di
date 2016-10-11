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
 * @internal
 */
class RegisterEntry
{
    private $type;
    private $spec;

    /**
     * RegisterEntry constructor.
     * @param int   $type
     * @param mixed $spec
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