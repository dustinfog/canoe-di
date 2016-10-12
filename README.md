#CanoeDI
一个非常简单且实用的IoC框架,相对于其他的Ioc框架有如下特点:

1. 配置简单: 大多数情况下几乎不需要额外的配置
2. 自动装配: 基于PHPDocument的property属性、构造函数的参数的类型和名称来自动装配
3. 懒加载: 所有被注入的变量的实例都为即用即取, 不会产生内存垃圾
4. IDE友好: 因为利用的是PHP的标准规范, 兼容大部分IDE
5. 接口注入: 在容器中注册过的类,可以自动用于接口类型或其父类型属性的注入

##安装
composer require dustinfog/canoe-di

##使用
###获取实例
```php
class ClassA
{
}
//DI容器在处理类型时,会在第一次遇到的时候实例化,并且在以后使用中以单例的方式使用
$a = \Canoe\CanoeDI::get(ClassA::class);
```
###构造函数自动装配
```php
class ClassB 
{
}

class ClassA
{
    public $b;
    
    //DI容器会自动为ClassA准备好ClassB的实例,这里需要注意的是,不要出现环状依赖
    public function __construct(ClassB $b)
    {
        $this->b = $b;
    }
}

$a = \Canoe\CanoeDI::get(ClassA::class);
```
###基于标注的装配
```php
class ClassC
{
}


/**
 * @property ClassC $c
 */
class ClassA
{
    //需要引入一个trait,用以处理$c的获取
    use \Canoe\CanoeDITrait;
    
    public function test() {
        //这里可以直接使用
        print_r($this->c);
    }
}
```
###预先定义
上面的例子都是在运行时来实现自动装配的,但在某些时候可能需要手动预先创建一些定义,以备后续使,框架提供了简单的支持.
```php

Canoe\CanoeDI::registerDefinition(ClassA::class);
Canoe\CanoeDI::registerDefinition(function() {
    return new ClassB();
}, 'b');

```
###配置
大多数时候,预先定义都是写在配置文件里,可以用下列的方法加载配置:

```php
\Canoe\CanoeDI::loadConfig(
[
    'definitions' => [ //这里是定义
        ClassB::class,
    ],
    'beans' => [ //这里可以预定义一些实际的值
        'uid' => 5,
    ],
]);
```
###接口注入
接口的注入需要预先注册所需接口的类:
```php
interface InterfaceB
{
}

class ClassB implements InterfaceB
{ 
}

class ClassA
{
    public $b;
    public function __construct(InterfaceB $b)
    {
        $this->b = $b;
    }
}

//因为预先注册了ClassB,那么所有ClassB的父类和实现的接口都有可能成为ClassA构造函数的候选
Canoe\CanoeDI::registerDefinition(ClassB::class);

$a = \Canoe\CanoeDI::get(ClassA::class);
```
###自动装配的发现规则

自动装配会优先去找与变量名匹配的实例,拿接口注入一节的示例代码举例:
1. DI会优先查找id为'b'的实例或者定义,如果找到的与需要的相匹配,则使用该实例
2. 如果找不到id为'b'的实例,或者该实例与所需的不匹配,则使用依赖的类型去查找或构造实例

