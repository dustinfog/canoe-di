#CanoeDI
一个非常简单且实用的IoC框架,相对于其他的Ioc框架有如下特点:

1. 高效: 框架使用了非常实用且高效的算法，使得框架本身对应用的影响微乎其微，且框架提供了C扩展，最大限度的将性能提升到最高。
2. 配置简单: 大多数情况下几乎不需要额外的配置
3. 自动装配: 基于PHPDocument的property属性来自动装配
4. 懒加载: 所有被注入的变量的实例都为即用即取, 不会产生内存垃圾
5. IDE友好: 因为利用的是PHP的标准规范, 兼容大部分IDE

##安装

编译安装，可以得到最大的效率：

```bash
$ git clone https://github.com/dustinfog/canoe-di.git
$ cd canoe-di/ext
$ phpize
$ ./configure
$ make
$ sudo make install
```
而后编辑php.ini

```ini
[canoe-di]
extension=canoe_di.so

```

composer安装 (生产环境如果已经编译安装了扩展，此步骤可省略。在开发环境，PHP源码可以让IDE提供代码完成提示，所以仍然推荐执行这一步)：

```bash
composer require dustinfog/canoe-di
```

##使用
###获取实例
```php
class ClassA
{
}
//DI容器在处理类型时,会在第一次遇到的时候实例化,并且在以后使用中以单例的方式使用。
$a = \Canoe\DI\Context::get(ClassA::class);
```
###基于标注的装配

```php

class ClassC
{
}

use \Canoe\DI\DITrait;
use \Canoe\DI\Context;
/**
 * @property ClassC $c
 */
class ClassA
{
    //需要引入一个trait,用以处理$c的获取
    use DITrait;
    
    public function test() {
        //这里可以直接使用
        print_r($this->c);
    }
}


$a = Context::get(ClassA::class);
$a->test(); //试一下会发生什么

```

###@uses标注：
uses可以指定属性使用的类或者容器里的实例

```php
interface InterfaceC {
    public function sayHello();
}


class ClassWorld implements InterfaceC
{
    public function sayHello() {
    	echo "hello, world!\n";
    }
}


class ClassC implements InterfaceC
{
	private $name;
	
	public function __construct($name)
	{
		$this->name = $name;
	}
	
    public function sayHello() {
    	echo "hello, $name!\n";
    }
}

use \Canoe\DI\DITrait;
use \Canoe\DI\Context;

/**
 * @property InterfaceC $c1 {@uses ClassWorld} //使用类名
 * @property InterfaceC $c2 {@uses c2} //使用容器内的ID
 */
class ClassA
{
    //需要引入一个trait,用以处理$c的获取
    use DITrait;
    
    public function test() {
        print_r($this->c1);
        print_r($this->c2);
    }
}

Context::set("c2", new ClassC("Bob"));
// 更好的选择：Context::registerDefinition("c2", function(){new ClassC("Bob")})

$a = Context::get(ClassA::class);
$a->test(); //试一下会发生什么
```
### Singleton
有时候，我们需要在一个非DI环境里有限的使用DI，这时候每个系统与DI容器的先借点都在调用Context::get()显得很丑陋，框架里提供了一个更加亲民的调用方式：

```php

use \Canoe\DI\SingletonTrait;

class ClassA
{
    use SingletonTrait;
}

$a = ClassA::getInstance();
// 与Context::get(ClassA::class)等价，但隐藏了Context调用。

$a = ClassA::getInstance("a1");
// 与Context::get("a1")等价，但做了进一步的类型检查，即a1取到的实例与ClassA必须有"is a"的关系。


```

###预先定义
上面的例子都是在运行时来实现自动装配的,但在某些时候可能需要手动预先创建一些定
义,以备后续使,框架提供了简单的支持.

```php
//注册类
Canoe\DI\Context::registerDefinition('a', ClassA::class);
//注册回调
Canoe\DI\Context::registerDefinition(
	'b',
	function() {
   		return new ClassB();
	}
);
//注册实例
Canoe\DI\Context::set('c', new ClassC());

```
###配置
大多数时候,预先定义都是写在配置文件里,可以用下列的方法加载配置:

```php
\Canoe\DI\Context::loadConfig(
[
    'definitions' => [ //这里是定义
        ClassB::class,
    ],
    'beans' => [ //这里可以预定义一些实际的值
        'uid' => 5,
    ],
]);
```
