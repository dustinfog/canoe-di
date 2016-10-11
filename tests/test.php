<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 11/10/2016
 * Time: 7:38 PM
 */

require_once __DIR__.'/../vendor/autoload.php';

interface InterfaceB{

}

class ClassB implements InterfaceB
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
    use \MyDI\DITrait;
    /**
     * @var ClassB
     */
    public $b;
    public $uid;

    /**
     * ClassA constructor.
     * @param InterfaceB $b
     * @param int        $uid
     */
    public function __construct(InterfaceB $b, $uid)
    {
        $this->b = $b;
        $this->uid = $uid;
    }

}

\MyDI\Container::register(ClassB::class, \MyDI\RegisterType::TYPE_CLASS);
\MyDI\Container::register(5, \MyDI\RegisterType::TYPE_VALUE, 'uid');

/** @var ClassA $a */
$a = \MyDI\Container::get(ClassA::class);

echo $a->b . PHP_EOL;
echo $a->c . PHP_EOL;
echo $a->uid . PHP_EOL;
