<?php
error_reporting(E_ALL);
require __DIR__ . DIRECTORY_SEPARATOR . "ProxyMethod.php";

define("EOL", PHP_EOL);
define("EOL2", str_repeat(PHP_EOL, 2));

class Base {
    private function test() {
        echo "test inheritance" . EOL;
    }
    private static function staticTest() {
        echo "test inheritance, static" . EOL;
    }
}

class Hello extends  Base{
    private static $name;
    private function __construct($name, $option = null)
    {
        self::$name = $name;
    }
    public static function getIns($name, $option = null)
    {
        return new self($name, $option);
    }
    private static function hello($prefix) {
        $name = self::$name;
        echo "Hello, $prefix $name!" . EOL;
	}
    private function hi($prefix) {
        $name = self::$name;
        echo "Hi, $prefix $name!" . EOL;
    }
}

echo "Test1" . EOL;
$test = Hello::getIns("xiaohong");
$say1 = new ProxyMethod($test);

$say1->hi("Miss."); // Hi, Miss. xiaohong!
$say1::hello("Miss."); // Hello, Miss. xiaohong!
$say1->test(); // test inheritance
$say1::staticTest(); // test inheritance, static

$say1->export("hello");
$say1->export("hi");
hi($test, "Miss."); // Hi, Miss. xiaohong!
hello("Miss."); // Hello, Miss. xiaohong!


echo EOL2;
echo "Test2" . EOL;
$say2 = new ProxyMethod("Hello", "xiaoming");
$say2->hi("Mr."); // Hi, Mr. xiaoming!
$say2::hello("Mr."); // Hello, Mr. xiaoming!
$say2->test(); // test inheritance
$say2::staticTest(); // test inheritance, static
$say2->alias("hi", "nihao1");
$say2->nihao1("Mr."); // Hi, Mr. xiaoming!
$say2->rename("hi", "nihao2");
$say2->nihao2("Mr."); // Hi, Mr. xiaoming!
//$proxy2->hi("Mr."); // Exception

echo EOL2;
echo "Test3" . EOL;
$stack = new ProxyMethod("SplStack");
foreach(range(0, 9) as $i) {
    $stack->push($i);
}
foreach(range(0, 9) as $i) {
    echo $stack->pop() . " ";
}
