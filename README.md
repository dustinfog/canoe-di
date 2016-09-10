#MyDI
##基本用例
###用例代码
```php
require_once(/*autoload策略*/);

/**
 * Class Dependency
 */
class Dependency {
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * hasdfasdfadsf
     */
    public function sayHello()
    {
        echo $this->message . PHP_EOL;
    }
}

/**
 * Class Test
 *
 * @property-read Dependency $dp A property need to be injected
 */
class Test
{
    use IocSupporter;
    /**
     * A property used to instantiate a Dependency instance, injecting to $dp property of current class
     */
    private $message = 'hello,world';

    public function fun() {
        //dp has been usable, and would output "hello,world"
        $this->dp->sayHello();
    }
}

$test = new Test();
$test->fun();
```
