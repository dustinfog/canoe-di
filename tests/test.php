<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 11/10/2016
 * Time: 7:38 PM
 */

require_once __DIR__.'/../vendor/autoload.php';

class ClassB
{
    public function __toString()
    {
        return __CLASS__;
    }
}
class ClassC
{
    public function __toString()
    {
        return __CLASS__;
    }
}

/**
 * Class ClassA
 * @property ClassC $c
 */
class ClassA
{
    /**
     * @var ClassB
     */
    public $b;
    public function __construct(ClassB $b)
    {
        $this->b = $b;
    }

    use \MyDI\DITrait;
}

/** @var ClassA $a */
$a = \MyDI\Container::get(ClassA::class);

echo $a->b;
