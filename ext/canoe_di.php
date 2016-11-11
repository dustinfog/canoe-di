<?php
namespace Haha;

interface Inter {
}
/**
 * @property-read int $id
 */
class Base implements Inter {
}

/**
 * hahahahaha
 * @property-read string $name
*/
class Test extends Base {
	use \Canoe\DI\DITrait;
	private $prop;
	public function __construct($prop = 1000){
		$this->prop = $prop;
	}
}

//$ret1 = \Canoe\Utils\DocProperty::parse(Test::class);
//$ret2 = \Canoe\Utils\DocProperty::parse(Test::class);
//var_dump($ret2);
// foreach (DocProperty::parse(Test::class) as $key => $property) {
// 	echo "==".$key."==".PHP_EOL;
// 	echo $property->getName().PHP_EOL;
// 	echo $property->getAccess().PHP_EOL;
// 	echo $property->getType().PHP_EOL;
// }

use \Canoe\DI\Context;

//Context::registerDefinition(function(){return "world";}, "hello1");
Context::set("id", 123);
Context::set("name", "Hello");
//Context::registerDefinition(Test::class);
//print_r(Context::get("de"));
//print_r(Context::get("good"));

$test1 = Context::get(Test::class);
//$test2 = Context::get(Test::class);
//debug_zval_dump($test1);
print_r($test1->id);
print_r($test1->name);
//debug_zval_dump($test1->name);

