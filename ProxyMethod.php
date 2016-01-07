<?php
/**
 * Class ProxyMethod
 * @author xiaofeng
 * 代理类的方法
 * 可以代理private protected方法，不使用setAccessible，无副作用
 */
class ProxyMethod
{
    private static $staticMethods = []; // bug
    private $methods = [];

    /* @var ReflectionClass $refClass */
    private $refClass;
    private $ctx;

    /**
     * @param mixed $class classname or object
     * @param mixed ctor arg1 , ctor arg2...
     * Proxy constructor.
     */
    public function __construct()
    {
        list($this->refClass, $this->ctx) =self::getRefCtx(func_get_args());
        $this->proxy();
    }

    public function __destruct()
    {
        self::$staticMethods = [];
    }

    public static function __callStatic($name, $args)
    {
        if(!isset(self::$staticMethods[$name])) {
            throw new BadMethodCallException ("method::{$name} does not exists.");
        }
        /* @var ReflectionMethod $method */
        $method = self::$staticMethods[$name];
        // return $method->invokeArgs(null, $args);
        // pass Accessible
        return call_user_func_array($method->getClosure(), $args);
    }

    public function __call($name, $args)
    {
        if(!isset($this->methods[$name])) {
            throw new BadMethodCallException ("method->{$name} does not exists.");
        }
        /* @var ReflectionMethod $method */
        $method = $this->methods[$name];
        // return $method->invokeArgs($this->ctx, $args);
        // pass Accessible
        return call_user_func_array($method->getClosure($this->ctx), $args);
    }

    private function proxy()
    {
        // filter do not work
        $allMethods = $this->refClass->getMethods();
        /* @var ReflectionMethod $method */
        foreach($allMethods as $method) {
            if($method->isAbstract()) {
                continue;
            }
            // no side effect
            // $method->setAccessible(true);
            if($method->isStatic()) {
                self::$staticMethods[$method->name] = $method;
            } else {
                $this->methods[$method->name] = $method;
            }
        }
    }

    /**
     * rename method name
     * @param  string  $name       old method name
     * @param  string  $newName   new method name
     * @param  boolean $deleteOld
     * @throws InvalidArgumentException
     * @return null
     */
    public function rename($name, $newName, $deleteOld = true)
    {
        if(isset($this->methods[$newName]) || isset(self::$staticMethods[$newName])) {
            throw new InvalidArgumentException("rename error: [class.{$this->refClass->name} method.{$newName}] has already exists.");
        }

        if(isset($this->methods[$name])) {
            $this->methods[$newName] = $this->methods[$name];
            if($deleteOld) {
                unset($this->methods[$name]);
            }
        } else if(isset(self::$staticMethods[$name])) {
            self::$staticMethods[$newName] = self::$staticMethods[$name];
            if($deleteOld) {
                unset(self::$staticMethods[$name]);
            }
        } else {
            throw new InvalidArgumentException("rename error: [class.{$this->refClass->name} method.{$name}] do not exists.");
        }
    }

    /**
     * alias method
     * @param  string  $name       old method name
     * @throws InvalidArgumentException
     * @return null
     */
    public function alias($name, $alisaName)
    {
        $this->rename($name, $alisaName, false);
    }

    /**
     * getClosure
     * @param $name
     * @return Closure
     */
    public function getClosure($name)
    {
        $methods = $this->methods + self::$staticMethods;
        if(!isset($methods[$name])) {
            throw new BadMethodCallException ("class.{$this->refClass->name} method.{$name} does not exists.");
        }

        /* @var ReflectionMethod $method */
        $method = $methods[$name];
        if($method->isStatic()) {
            return $method->getClosure();
        } else {
            return $method->getClosure($this->ctx);
        }
    }

    /**
     * export method to global function
     * @param  string $name
     * @param  string $exportName
     * @return null
     */
    public function export($name, $exportName = "")
    {
        $methods = $this->methods + self::$staticMethods;
        if(!isset($methods[$name])) {
            throw new BadMethodCallException ("class.{$this->refClass->name} method.{$name} does not exists.");
        }

        if(!$exportName) {
            $exportName = $name;
        }

        if(function_exists($exportName)) {
            throw new InvalidArgumentException("function.{$exportName} has already exists.");
        }

        /* @var ReflectionMethod $method */
        $method = $methods[$name];
        if($method->isStatic()) {
            // export static method
            $func = <<<FUNC
function {$exportName}() {
    \$ref_method = new ReflectionMethod("{$method->class}", "{$method->name}");
	return call_user_func_array(\$ref_method->getClosure(), func_get_args());
}
FUNC;
        } else {
            $class_name = $this->refClass->name;
            // export not static method
            // avoid setAccessible(true) using getClosure
            $func = <<<FUNC
function {$exportName}() {
    if(func_num_args() < 1) {
        throw new InvalidArgumentException("first argument must be object intanceof {$class_name}).");;
    }
    \$args = func_get_args();
    \$class = array_shift(\$args);
    \$ctx = null;
    if(is_object(\$class) && (\$class instanceof {$class_name})) {
        \$ctx = \$class;
    } else {
        throw new InvalidArgumentException("first argument must be object intanceof {$class_name}).");;
    }
    \$ref_method = new ReflectionMethod(\$ctx, "{$method->name}");
    return call_user_func_array(\$ref_method->getClosure(\$ctx), \$args);
}
FUNC;
        }

        self::myEval($func);
    }

    /**
     * get refClass and Ctx object
     * @param array $args
     * @return array
     */
    private static function getRefCtx(array $args)
    {
        $ctx = null;
        $refClass = null;
        $e = new InvalidArgumentException("first argument must be string(className with ctor parameter) or object.");

        if (count($args) < 1) {
            throw $e;
        }

        $class = array_shift($args);
        if (is_object($class)) {
            $refClass = new ReflectionClass($class);
            $ctx = $class;
        } else if (is_string($class)) {
            if (!class_exists($class)) {
                throw $e ;
            }
            $refClass = new ReflectionClass($class);
            $className = $refClass->name;
            if ($ctor = $refClass->getConstructor()) {
                if($paras = $refClass->getConstructor()->getParameters()) {
                    $at_least_ctor_paras_num = count($paras);
                    /* @var ReflectionParameter $para*/
                    foreach($paras as $para) {
                        if($para->isOptional()) {
                            $at_least_ctor_paras_num--;
                        }
                    }
                    $left_arg_num = count($args);
                    if ($left_arg_num < $at_least_ctor_paras_num) {
                        throw new InvalidArgumentException("class $className ctor need $at_least_ctor_paras_num parameters, but $left_arg_num be given.");
                    }
                }

                if($ctor->isPublic()) {
                    $ctx = $refClass->newInstanceArgs($args);
                } else {
                    $ctx = $refClass->newInstanceWithoutConstructor();
                    $ctor->setAccessible(true);
                    $ctor->invokeArgs($ctx, $args);
                    $ctor->setAccessible(false);
                }
            } else {
                $ctx = $refClass->newInstanceArgs();
            }
        } else {
            throw $e;
        }

        return [$refClass, $ctx];
    }

    private static function myEval($code)
    {
        $flag = false;
        eval('$flag = true;');
        if($flag) {
            return eval($code);
        } else {
            file_put_contents($tmp = tempnam(sys_get_temp_dir(), "eval"), "<?php\n$code");
            return include($tmp);
        }
    }
}

